<?php

namespace Kanboard\Plugin\SortaFolders\Model;

use Kanboard\Core\Base;

/**
 * Health signals for the folder page: a green, yellow or red light per project and folder, the quiet
 * days since a project's last task activity, and a 12-week activity count. Everything here works on
 * project ids the caller already limited to what the viewer may see, so nothing in it widens access.
 */
class HealthModel extends Base
{
    const RED_PCT = 'sortafolders_health_red_pct';
    const RED_QUIET = 'sortafolders_health_red_quiet';
    const YELLOW_QUIET = 'sortafolders_health_yellow_quiet';
    const SOON_DAYS = 'sortafolders_health_soon_days';
    const WEEKS = 12;

    /**
     * Setting key => array(short name, default, lowest, highest).
     */
    public static function getSettingDefinitions()
    {
        return array(
            self::RED_PCT     => array('red_pct', 25, 1, 100),
            self::RED_QUIET   => array('red_quiet', 30, 1, 365),
            self::YELLOW_QUIET => array('yellow_quiet', 14, 1, 365),
            self::SOON_DAYS   => array('soon_days', 7, 1, 365),
        );
    }

    /**
     * An integer inside the range, or the default for anything else (empty, text, a decimal, out of range).
     */
    public static function cleanSetting($key, $value)
    {
        $def = self::getSettingDefinitions();
        $def = $def[$key];

        if (! is_scalar($value) || ! preg_match('/^\s*\d{1,6}\s*$/', (string) $value)) {
            return $def[1];
        }

        $number = (int) $value;

        return ($number < $def[2] || $number > $def[3]) ? $def[1] : $number;
    }

    /**
     * Short name => value, for example array('red_pct' => 25, ...).
     */
    public function getSettings()
    {
        $settings = array();

        foreach (self::getSettingDefinitions() as $key => $def) {
            $settings[$def[0]] = self::cleanSetting($key, $this->configModel->get($key, (string) $def[1]));
        }

        return $settings;
    }

    /**
     * Config key => cleaned value, ready for configModel->save().
     */
    public function cleanValues(array $values)
    {
        $clean = array();

        foreach (self::getSettingDefinitions() as $key => $def) {
            $clean[$key] = (string) self::cleanSetting($key, isset($values[$def[0]]) ? $values[$def[0]] : null);
        }

        return $clean;
    }

    /**
     * The light for one project. $totals is a getTaskTotals() entry, $lastModified is projects.last_modified.
     * Returns array(level red|yellow|green|none, quiet_days or null, overdue, open, reason).
     */
    public function assess(array $totals, $lastModified, array $settings, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $open = (int) $totals['open'];
        $overdue = (int) $totals['overdue'];
        $soon = (int) $totals['soon'];
        $quiet = (int) $lastModified > 0 ? max(0, (int) floor(($now - (int) $lastModified) / 86400)) : null;

        $base = array('quiet_days' => $quiet, 'overdue' => $overdue, 'open' => $open);

        if ($open === 0) {
            return $base + array('level' => 'none', 'reason' => t('No open tasks'));
        }

        $reasons = array();

        if ($overdue > 0) {
            $reasons[] = t('%d overdue', $overdue);
        }

        if ($soon > 0) {
            $reasons[] = t('%d due in %d days', $soon, $settings['soon_days']);
        }

        if ($quiet !== null && $quiet >= $settings['yellow_quiet']) {
            $reasons[] = t('quiet for %d days', $quiet);
        }

        $red = ($overdue > 0 && $overdue * 100 >= $settings['red_pct'] * $open)
            || ($quiet !== null && $quiet >= $settings['red_quiet']);

        if ($red) {
            $level = 'red';
        } elseif (! empty($reasons)) {
            $level = 'yellow';
        } else {
            $level = 'green';
            $reasons[] = t('On track');
        }

        return $base + array('level' => $level, 'reason' => implode(', ', $reasons));
    }

    /**
     * Rank of a level for "worst of": none is never ranked.
     */
    public static function rank($level)
    {
        $ranks = array('red' => 3, 'yellow' => 2, 'green' => 1);

        return isset($ranks[$level]) ? $ranks[$level] : 0;
    }

    public static function worst(array $levels)
    {
        $best = 'none';

        foreach ($levels as $level) {
            if (self::rank($level) > self::rank($best)) {
                $best = $level;
            }
        }

        return $best;
    }

    /**
     * Adds 'health' to every node: the worst light among its projects and subfolders. Each project row
     * needs an 'sf_health' key.
     */
    public function addNodeHealth(array $nodes)
    {
        foreach ($nodes as $i => $node) {
            $nodes[$i]['children'] = $this->addNodeHealth($node['children']);
            $levels = array();

            foreach ($node['projects'] as $project) {
                $levels[] = $project['sf_health']['level'];
            }

            foreach ($nodes[$i]['children'] as $child) {
                $levels[] = $child['health'];
            }

            $nodes[$i]['health'] = self::worst($levels);
        }

        return $nodes;
    }

    /**
     * Up to $limit projects that are red or yellow, red first, then most overdue, then longest quiet.
     * $projects is a list of project rows carrying 'sf_health'.
     */
    public function attention(array $projects, $limit = 5)
    {
        $list = array_values(array_filter($projects, function ($project) {
            return self::rank($project['sf_health']['level']) >= 2;
        }));

        usort($list, function ($a, $b) {
            $x = $a['sf_health'];
            $y = $b['sf_health'];

            $result = self::rank($y['level']) - self::rank($x['level']);

            if ($result === 0) {
                $result = $y['overdue'] - $x['overdue'];
            }

            if ($result === 0) {
                $result = (int) $y['quiet_days'] - (int) $x['quiet_days'];
            }

            return $result !== 0 ? $result : (int) $a['id'] - (int) $b['id'];
        });

        return array_slice($list, 0, $limit);
    }

    /**
     * Project id => list of 12 weekly activity counts, oldest week first, the last one ending now.
     * One query for all the ids given.
     */
    public function getSparklines(array $projectIds, $now = null)
    {
        $now = $now === null ? time() : (int) $now;
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));
        $lines = array();

        foreach ($projectIds as $id) {
            $lines[$id] = array_fill(0, self::WEEKS, 0);
        }

        if (empty($projectIds)) {
            return $lines;
        }

        $week = 7 * 86400;
        $rows = $this->db->table('project_activities')
            ->columns('project_id', 'date_creation')
            ->in('project_id', $projectIds)
            ->gt('date_creation', $now - self::WEEKS * $week)
            ->lte('date_creation', $now)
            ->findAll();

        foreach ($rows as $row) {
            $age = (int) floor(($now - (int) $row['date_creation']) / $week);

            if ($age >= 0 && $age < self::WEEKS) {
                $lines[(int) $row['project_id']][self::WEEKS - 1 - $age]++;
            }
        }

        return $lines;
    }

    /**
     * Keeps the projects that match a filter chip. Each project carries "sf_totals" and "sf_health". The projects handed
     * in are already limited to what the viewer can see.
     */
    public function filterProjects(array $projects, $filter, array $settings, $userId)
    {
        if ($filter === 'all') {
            return $projects;
        }

        $mine = $filter === 'mine'
            ? array_flip($this->getProjectsWithOpenTaskFor($userId, array_keys($projects)))
            : array();

        return array_filter($projects, function ($project) use ($filter, $settings, $mine) {
            $h = $project['sf_health'];

            switch ($filter) {
                case 'overdue':
                    return $project['sf_totals']['overdue'] > 0;
                case 'soon':
                    return $project['sf_totals']['soon'] > 0;
                case 'today':
                    return $project['sf_totals']['today'] > 0;
                case 'unowned':
                    return $project['sf_totals']['unowned'] > 0;
                case 'blocked':
                    return $project['sf_totals']['blocked'] > 0;
                case 'stuck':
                    return $project['sf_totals']['stuck'] > 0;
                case 'quiet':
                    return $h['quiet_days'] !== null && $h['quiet_days'] >= $settings['yellow_quiet'];
                case 'attention':
                    return $h['level'] === 'red' || $h['level'] === 'yellow';
                case 'mine':
                    return isset($mine[(int) $project['id']]);
            }

            return true;
        });
    }

    /**
     * How many of the projects each filter tab would keep, key => count, for dimming the tabs with nothing behind them.
     */
    public function filterCounts(array $projects, array $keys, array $settings, $userId)
    {
        $counts = array();

        foreach ($keys as $key) {
            $counts[$key] = count($this->filterProjects($projects, $key, $settings, $userId));
        }

        return $counts;
    }

    /**
     * Ids of the given projects where the user has an open task assigned.
     */
    public function getProjectsWithOpenTaskFor($userId, array $projectIds)
    {
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));

        if (empty($projectIds) || (int) $userId <= 0) {
            return array();
        }

        return array_map('intval', $this->db->table('tasks')
            ->distinct()
            ->in('project_id', $projectIds)
            ->eq('owner_id', (int) $userId)
            ->eq('is_active', 1)
            ->findAllByColumn('project_id'));
    }
}
