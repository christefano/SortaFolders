<?php

namespace Kanboard\Plugin\SortaFolders\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\SortaFolders\Controller\FolderController;

/**
 * Registered as $this->sortaFolders for templates.
 */
class FolderHelper extends Base
{
    const TERM_KEY = 'sortafolders_term';

    /**
     * Term choices for the setting: key => array(singular, plural, label). "default" keeps the folder wording.
     */
    public static function getTerms()
    {
        return array(
            'clients'        => array('client', 'clients', 'Clients'),
            'departments'    => array('department', 'departments', 'Departments'),
            'default'        => array('folder', 'folders', 'Folders (Default)'),
            'portfolios'     => array('portfolio', 'portfolios', 'Portfolios'),
            'products'       => array('product', 'products', 'Products'),
            'programs'       => array('program', 'programs', 'Programs'),
            'project_groups' => array('project group', 'project groups', 'Project groups'),
            'spaces'         => array('space', 'spaces', 'Spaces'),
        );
    }

    const ICON_KEY = 'sortafolders_icon';
    const MOTD_KEY = 'sortafolders_motd';
    const ATTENTION_KEY = 'sortafolders_attention';
    const HIDDEN_KEY = 'sortafolders_hidden';  // comma-separated folder ids hidden from every folder list

    /**
     * Whether a folder's description shows at the top of the projects in it (off until an admin turns it on).
     */
    public function attentionEnabled()
    {
        return (string) $this->configModel->get(self::ATTENTION_KEY, '0') === '1';
    }

    public function motdEnabled()
    {
        return (string) $this->configModel->get(self::MOTD_KEY, '0') === '1';
    }

    /**
     * 3 hours as "3h", 3.5 hours as "3:30h".
     */
    public function hours($hours)
    {
        return $this->hoursFormat($hours);
    }

    /**
     * The managers of one project as links to their profiles, or plain names when the viewer may not open a profile.
     * Core lets any signed-in user open the public profile page (UserViewController, profile), so the link is
     * left out only when that stops being true.
     */
    public function managerList(array $managers)
    {
        $links = array();

        foreach ($managers as $manager) {
            $links[] = $this->userLink($manager['id'], $this->helper->user->getFullname($manager));
        }

        return implode(', ', $links);
    }

    /**
     * A person's name as a link to their public profile, or the plain name when the viewer may not open a profile
     * or there is no such user (id 0).
     */
    public function userLink($userId, $name)
    {
        $name = $this->helper->text->e($name);

        if ((int) $userId > 0 && $this->helper->user->hasAccess('UserViewController', 'profile')) {
            return $this->helper->url->link($name, 'UserViewController', 'profile', array('user_id' => (int) $userId));
        }

        return $name;
    }

    /**
     * Icon choices for the setting: Font Awesome 4 name (shipped with Kanboard) => label. The first is the default.
     */
    public static function getIcons()
    {
        return array(
            'folder-o'   => 'Folder',
            'briefcase'  => 'Briefcase',
            'building-o' => 'Building',
            'sitemap'    => 'Sitemap',
            'cube'       => 'Cube',
            'cubes'      => 'Cubes',
            'tags'       => 'Tags',
            'bookmark-o' => 'Bookmark',
            'star-o'     => 'Star',
            'users'      => 'Users',
            'th-large'   => 'Grid',
            'archive'    => 'Archive',
            'suitcase'   => 'Suitcase',
            'flag-o'     => 'Flag',
            'globe'      => 'Globe',
            'rocket'     => 'Rocket',
            'home'       => 'Home',
            'lightbulb-o' => 'Lightbulb',
            'puzzle-piece' => 'Puzzle piece',
            'university' => 'University',
        );
    }

    public function getIconKey()
    {
        $key = (string) $this->configModel->get(self::ICON_KEY, 'folder-o');

        return array_key_exists($key, self::getIcons()) ? $key : 'folder-o';
    }

    /**
     * Font Awesome class name for a folder. The default icon has an open variant.
     */
    public function icon($open = false)
    {
        $key = $this->getIconKey();

        return ($open && $key === 'folder-o') ? 'folder-open-o' : $key;
    }

    /**
     * "8 open", "8 open and 2 overdue", or "8 open, 2 overdue and 3 due in 7 days".
     */
    public function totalsText(array $totals)
    {
        $parts = array(t('%d open', (int) $totals['open']));

        if ($totals['overdue'] > 0) {
            $parts[] = t('%d overdue', (int) $totals['overdue']);
        }

        if ($totals['soon'] > 0) {
            $settings = $this->sortaHealthModel->getSettings();
            $parts[] = t('%d due in %d days', (int) $totals['soon'], $settings['soon_days']);
        }

        $last = array_pop($parts);

        return empty($parts) ? $last : implode(', ', $parts).' '.t('and').' '.$last;
    }

    /**
     * The folder page header and each folder bar: "21 projects, 97 open tasks, 30 open subtasks, and 4 overdue", or "1 project, 0 open tasks, and 2 open subtasks".
     */
    public function summaryText($projectCount, array $totals)
    {
        $open = (int) $totals['open'];
        $subtasks = isset($totals['subtasks']) ? (int) $totals['subtasks'] : 0;
        $parts = array(
            $projectCount === 1 ? t('%d project', 1) : t('%d projects', $projectCount),
            $open === 1 ? t('%d open task', 1) : t('%d open tasks', $open),
            $subtasks === 1 ? t('%d open subtask', 1) : t('%d open subtasks', $subtasks),
        );

        if ($totals['overdue'] > 0) {
            $parts[] = t('%d overdue', (int) $totals['overdue']);
        }

        if ($totals['soon'] > 0) {
            $settings = $this->sortaHealthModel->getSettings();
            $parts[] = t('%d due in %d days', (int) $totals['soon'], $settings['soon_days']);
        }

        $last = array_pop($parts);

        return implode(', ', $parts).', '.t('and').' '.$last;
    }

    /**
     * "3:30h spent and 12.5h estimated" over every task, open and closed. Time spent shows minutes as 3:30h, the estimate as decimal hours. Empty when neither was logged.
     */
    public function hoursText(array $totals)
    {
        $parts = array();
        $spent = isset($totals['hours']) ? (float) $totals['hours'] : 0.0;
        $estimated = isset($totals['estimated']) ? (float) $totals['estimated'] : 0.0;

        if ($spent > 0) {
            $parts[] = t('%s spent', $this->hoursFormat($spent));
        }

        if ($estimated > 0) {
            $parts[] = t('%s estimated', rtrim(rtrim(number_format($estimated, 2, '.', ''), '0'), '.').'h');
        }

        return implode(' '.t('and').' ', $parts);
    }

    /**
     * 3 hours as "3h", 3.5 hours as "3:30h".
     */
    private function hoursFormat($hours)
    {
        $minutes = (int) round($hours * 60);

        return intdiv($minutes, 60).($minutes % 60 > 0 ? sprintf(':%02d', $minutes % 60) : '').'h';
    }

    /**
     * Share of tasks that are closed, 0 to 100. A project with no tasks shows an empty bar.
     */
    public function progress(array $totals)
    {
        $all = (int) $totals['open'] + (int) $totals['closed'];

        return $all > 0 ? (int) round(100 * (int) $totals['closed'] / $all) : 0;
    }

    /**
     * "Last activity: Oct 1, 2026", or an empty string when the date is unknown.
     */
    public function lastActivity($timestamp)
    {
        return $timestamp > 0 ? t('Last activity').': '.$this->helper->dt->date($timestamp) : '';
    }

    /**
     * Link to BigBoard filtered to one folder. Empty when BigBoard is not installed.
     */
    public function bigboardLink($folderId)
    {
        if (! class_exists('\\Kanboard\\Plugin\\Bigboard\\Plugin')) {
            return '';
        }

        return $this->helper->url->icon(
            'th-large',
            t('Open in BigBoard'),
            'Bigboard',
            'index',
            array('plugin' => 'Bigboard', 'scope' => 'group:'.(int) $folderId)
        );
    }

    /**
     * Whether the signed-in user may create folders: an admin, or a Project Manager of any project.
     */
    public function canCreateFolder()
    {
        return $this->sortaFolderModel->canCreate($this->userSession->getId(), $this->userSession->isAdmin());
    }

    /**
     * One health setting by its short name (red_pct, red_quiet, yellow_quiet, soon_days).
     */
    public function healthSetting($name)
    {
        $settings = $this->sortaHealthModel->getSettings();

        return $settings[$name];
    }

    /**
     * Why a folder has its light: how many of its visible projects are red and how many are yellow.
     */
    public function folderReason(array $node)
    {
        $red = 0;
        $yellow = 0;
        $count = function (array $n) use (&$count, &$red, &$yellow) {
            foreach ($n['projects'] as $project) {
                $level = $project['sf_health']['level'];
                $red += $level === 'red' ? 1 : 0;
                $yellow += $level === 'yellow' ? 1 : 0;
            }

            foreach ($n['children'] as $child) {
                $count($child);
            }
        };
        $count($node);

        if ($red === 0 && $yellow === 0) {
            return t('On track');
        }

        $parts = array();

        if ($red > 0) {
            $parts[] = t('%d red', $red);
        }

        if ($yellow > 0) {
            $parts[] = t('%d yellow', $yellow);
        }

        return implode(', ', $parts);
    }

    /**
     * A status note flag: the word and an icon, never color alone.
     */
    public function flag($flag)
    {
        $reason = array('green' => t('Going well'), 'yellow' => t('Some concern'), 'red' => t('Serious concern'));

        return isset($reason[$flag]) ? $this->light($flag, $reason[$flag]) : '';
    }

    /**
     * Word, reason and icon of a note flag, for the note box (empty array for an unknown flag).
     */
    public function flagParts($flag)
    {
        $reason = array('green' => t('Going well'), 'yellow' => t('Some concern'), 'red' => t('Serious concern'));
        $words = array('red' => t('Red'), 'yellow' => t('Yellow'), 'green' => t('Green'));
        $icons = array('red' => 'times-circle', 'yellow' => 'exclamation-triangle', 'green' => 'check-circle');

        if (! isset($reason[$flag])) {
            return array();
        }

        return array('word' => $words[$flag], 'reason' => $reason[$flag], 'icon' => $icons[$flag]);
    }

    /**
     * A health light as words, an icon shape, and the reason as a tooltip, never color alone. Empty for "none".
     */
    public function light($level, $reason)
    {
        $words = array('red' => t('Red'), 'yellow' => t('Yellow'), 'green' => t('Green'));
        $icons = array('red' => 'times-circle', 'yellow' => 'exclamation-triangle', 'green' => 'check-circle');

        if (! isset($words[$level])) {
            return '';
        }

        return '<span class="sortafolders-light sortafolders-light-'.$level.'" title="'.$this->helper->text->e($reason).'">'
            .'<i class="fa fa-'.$icons[$level].' fa-fw" aria-hidden="true"></i>'
            .'<span class="sortafolders-sr">'.$this->helper->text->e($words[$level]).': </span>'
            .'<span class="sortafolders-sr">'.$this->helper->text->e($reason).'</span></span>';
    }

    /**
     * A 12-week activity sparkline as inline SVG, with the counts as its label.
     */
    public function sparkline(array $counts)
    {
        $w = 4;
        $gap = 1;
        $height = 16;
        $max = max(1, max($counts));
        $bars = '';

        foreach (array_values($counts) as $i => $count) {
            $h = $count > 0 ? max(2, (int) round($height * $count / $max)) : 1;
            $bars .= '<rect x="'.($i * ($w + $gap)).'" y="'.($height - $h).'" width="'.$w.'" height="'.$h.'"/>';
        }

        $label = t('Activity, last 12 weeks').': '.implode(', ', array_map('intval', $counts));

        return '<svg class="sortafolders-spark" width="'.(count($counts) * ($w + $gap)).'" height="'.$height.'" role="img" aria-label="'
            .$this->helper->text->e($label).'" fill="currentColor"><title>'.$this->helper->text->e($label).'</title>'.$bars.'</svg>';
    }

    /**
     * The label of the "soon" tab: "Due this week" while the setting is 7 days, else "Due in N days", so the label never says a week for a different number.
     */
    public function soonLabel()
    {
        $settings = $this->sortaHealthModel->getSettings();
        $days = (int) $settings['soon_days'];

        return $days === 7 ? t('Due this week') : ($days === 1 ? t('Due in 1 day') : t('Due in %d days', $days));
    }

    /**
     * The icon of a tab, by filter key.
     */
    public function tabIcon($key)
    {
        $icons = array(
            'all' => 'list', 'overdue' => 'exclamation-circle', 'today' => 'calendar-check-o', 'soon' => 'calendar', 'mine' => 'user',
            'unowned' => 'user-o', 'blocked' => 'lock', 'stuck' => 'hourglass-half', 'quiet' => 'moon-o', 'attention' => 'exclamation-triangle',
        );

        return isset($icons[$key]) ? $icons[$key] : 'list';
    }

    /**
     * The BigBoard search behind a tab, or "" for a tab BigBoard can't answer (All, Quiet, Needs attention).
     */
    public function tabSearch($key)
    {
        $settings = $this->sortaHealthModel->getSettings();
        $searches = array(
            'overdue' => 'status:open due:<today',
            'today'   => 'status:open due:today',
            'soon'    => 'status:open due:>=today due:"<='.$settings['soon_days'].' days"',
            'mine'    => 'status:open assignee:me',
            'unowned' => 'status:open assignee:nobody',
            'blocked' => 'status:open link:"is blocked by"',
            'stuck'   => 'status:open moved:<"-30 days"',
        );

        return isset($searches[$key]) ? $searches[$key] : '';
    }

    /**
     * Hover text for a filter tab, by page kind ("projects", "tasks" or "subtasks"): what a click shows, as one short action
     * sentence. $empty adds that nothing matches. Hover text in these plugins is always an action, as short as it can be,
     * has no commas and ends with a period.
     */
    public function tabTitle($kind, $key, $empty = false)
    {
        $settings = $this->sortaHealthModel->getSettings();
        $soon = (int) $settings['soon_days'];
        $quiet = (int) $settings['yellow_quiet'];
        $taskKind = $kind !== 'projects';
        $texts = array(
            'all'       => array(t('View every project.'), $kind === 'subtasks' ? t('View every task with a subtask for you.') : t('View every task assigned to you.')),
            'overdue'   => array(t('View projects with overdue tasks.'), $kind === 'subtasks' ? t('View overdue tasks with a subtask for you.') : t('View tasks past their due date.')),
            'today'     => array(t('View projects with tasks due today.'), $kind === 'subtasks' ? t('View tasks with a subtask for you due today.') : t('View tasks due today.')),
            'soon'      => array(t('View projects with tasks due within the next %d days.', $soon), $kind === 'subtasks' ? t('View tasks with a subtask for you due within the next %d days.', $soon) : t('View tasks due within the next %d days.', $soon)),
            'mine'      => array(t('View projects where you have an open task.'), t('View tasks you own.')),
            'unowned'   => array(t('View projects with tasks not assigned to anyone.'), t('View tasks not assigned to anyone.')),
            'blocked'   => array(t('View projects with tasks blocked by other tasks.'), t('View tasks blocked by other tasks.')),
            'stuck'     => array(t('View projects with tasks that have stayed in one column for 30 days.'), t('View tasks that have stayed in one column for 30 days.')),
            'quiet'     => array(t('View projects with no activity for %d days or more.', $quiet), t('View tasks in projects with no activity for %d days or more.', $quiet)),
            'attention' => array(t('View projects with overdue tasks or that have been quiet for %d days or more.', $quiet), t('View tasks in projects with overdue tasks or that have been quiet for %d days or more.', $quiet)),
        );
        $text = isset($texts[$key]) ? $texts[$key][$taskKind ? 1 : 0] : '';
        $none = array('projects' => t('No projects match this filter right now.'), 'tasks' => t('No tasks match this filter right now.'), 'subtasks' => t('No subtasks match this filter right now.'));

        return $empty ? $text.' '.$none[$kind] : $text;
    }

    /**
     * Hover text for the "Open in BigBoard" item of a tab. "projects" kind covers the folder page and My projects, where the
     * search looks at everyone's tasks. "tasks" and "subtasks" are the user's own.
     */
    public function bigboardTitle($kind, $key, $empty = false)
    {
        $settings = $this->sortaHealthModel->getSettings();
        $soon = (int) $settings['soon_days'];
        $own = $kind !== 'projects';
        $texts = array(
            'overdue' => array(t('View overdue tasks in BigBoard.'), t('View your overdue tasks in BigBoard.')),
            'today'   => array(t('View tasks due today in BigBoard.'), t('View your tasks due today in BigBoard.')),
            'soon'    => array(t('View tasks due within the next %d days in BigBoard.', $soon), t('View your tasks due within the next %d days in BigBoard.', $soon)),
            'mine'    => array(t('View your tasks in BigBoard.'), t('View your tasks in BigBoard.')),
            'unowned' => array(t('View tasks not assigned to anyone in BigBoard.'), t('View tasks not assigned to anyone in BigBoard.')),
            'blocked' => array(t('View tasks blocked by other tasks in BigBoard.'), t('View your tasks blocked by other tasks in BigBoard.')),
            'stuck'   => array(t('View tasks that have stayed in one column for 30 days in BigBoard.'), t('View your tasks that have stayed in one column for 30 days in BigBoard.')),
        );
        $text = isset($texts[$key]) ? $texts[$key][$own ? 1 : 0] : ($own ? t('View your tasks in BigBoard.') : t('Open BigBoard.'));

        return $empty ? $text.' '.($kind === 'subtasks' ? t('No subtasks match this search right now.') : t('No tasks match this search right now.')) : $text;
    }

    /**
     * Whether BigBoard is loaded.
     */
    public function hasBigboard()
    {
        return class_exists('\\Kanboard\\Plugin\\Bigboard\\Plugin');
    }

    /**
     * The BigBoard items of a folder's links menu: "Open in BigBoard" and one item for each search, the ones with no match
     * dimmed but still links. Empty without BigBoard. $totals holds the folder's counts.
     */
    public function bigboardMenuItems($folderId, array $totals)
    {
        if (! $this->hasBigboard()) {
            return '';
        }

        $e = function ($text) {
            return $this->helper->text->e($text);
        };
        $scope = array('plugin' => 'Bigboard', 'scope' => 'group:'.(int) $folderId);
        $items = array('<li><a href="'.$e($this->helper->url->href('Bigboard', 'index', $scope)).'" title="'.$e(t('Open this folder in BigBoard.')).'">'.$e(t('Open this folder in BigBoard')).'</a></li>');

        foreach (array('overdue', 'today', 'soon', 'mine', 'unowned', 'blocked', 'stuck') as $key) {
            $empty = (int) $totals[$key] === 0;
            $href = $e($this->helper->url->href('Bigboard', 'index', $scope + array('search' => $this->tabSearch($key))));
            $items[] = '<li'.($empty ? ' class="sortafolders-link-off"' : '').'><a href="'.$href.'" title="'.$e($this->bigboardTitle('projects', $key, $empty)).'">'.$e(rtrim($this->bigboardTitle('projects', $key), '.')).'</a></li>';
        }

        return implode('', $items);
    }

    private $termKey = null;

    /**
     * The admin-selected term key, "default" when none or an unknown one is stored.
     */
    public function getTermKey()
    {
        if ($this->termKey === null) {
            $key = (string) $this->configModel->get(self::TERM_KEY, 'default');
            $this->termKey = array_key_exists($key, self::getTerms()) ? $key : 'default';
        }

        return $this->termKey;
    }

    /**
     * Same as t(), but with "folder" in the English text swapped for the selected term.
     * The swap happens on the format string, so a folder or project name passed as an
     * argument is never touched.
     */
    public function t($text)
    {
        $args = func_get_args();
        $args[0] = $this->applyTerm($text);

        return call_user_func_array('t', $args);
    }

    public function applyTerm($text)
    {
        $key = $this->getTermKey();

        // "Project folders" is the name of the feature, so it is just "Folders" with the default term.
        if ($key === 'default') {
            return preg_replace_callback('/\bproject folders\b/i', function ($m) {
                return ctype_upper(substr($m[0], 0, 1)) ? 'Folders' : 'folders';
            }, $text);
        }

        $terms = self::getTerms();
        $singular = $terms[$key][0];
        $plural = $terms[$key][1];

        $cap = function ($match, $word) {
            return ctype_upper(substr($match, 0, 1)) ? ucfirst($word) : $word;
        };

        // "Project folders" is the name of the feature, so it becomes just the term.
        $text = preg_replace_callback('/\bproject folders\b/i', function ($m) use ($cap, $plural) {
            return $cap($m[0], $plural);
        }, $text);

        $text = preg_replace_callback('/\bsubfolder(s?)\b/i', function ($m) use ($cap, $singular, $plural) {
            return $cap($m[0], 'sub-'.($m[1] === 's' ? $plural : $singular));
        }, $text);

        return preg_replace_callback('/\bfolder(s?)\b/i', function ($m) use ($cap, $singular, $plural) {
            return $cap($m[0], $m[1] === 's' ? $plural : $singular);
        }, $text);
    }

    /**
     * Folder choices for a select, "No folder" first, limited to the folders the user can see.
     */
    public function getOptions()
    {
        return $this->sortaFolderModel->getFolderOptionsForUser($this->userSession->getId());
    }

    /**
     * Who filing a project into each of these folders would give access to (folder id => array('path' => label,
     * 'list' => list of array(user_id, name, role))), so an admin sees it before saving; empty for everyone else. Keyed by id because two
     * folders can share a path label. Shown on the New project and Edit folder forms.
     */
    public function getGrantNotes(array $options)
    {
        // Names of users and groups are for admins only: people who do not know of one another never see each other.
        if (! $this->userSession->isAdmin()) {
            return array();
        }

        $notes = $this->sortaAccessModel->getGrantNotes(array_filter(array_map('intval', array_keys($options))));
        $out = array();

        foreach ($notes as $folderId => $list) {
            $out[(int) $folderId] = array('path' => $this->sortaFolderModel->getPathLabel($folderId), 'list' => $list);
        }

        return $out;
    }

    /**
     * The project's folder as array('id', 'name', 'path', 'description'), or null when it has none.
     */
    public function getFolder($projectId)
    {
        $folderId = $this->sortaFolderModel->getFolderId($projectId);
        $folder = $folderId > 0 ? $this->sortaFolderModel->getById($folderId) : null;

        if ($folder === null) {
            return null;
        }

        return array(
            'id'   => $folder['id'],
            'name' => $folder['name'],
            'path' => $this->sortaFolderModel->getPathLabel($folder['id']),
            'description' => $folder['description'],
        );
    }
}
