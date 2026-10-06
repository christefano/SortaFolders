<?php

namespace Kanboard\Plugin\SortaFolders\Model;

use Kanboard\Core\Base;

/**
 * Counts for the tab bars on the dashboard pages. Every method takes the ids of the projects the viewer can see on that page
 * and reads nothing else, so a hidden project never counts. marks(), mine() and blockedCount() are small copies of the
 * ones in BusyBee's AnswerModel: a plugin should not reach into another plugin's private methods, and the tests catch drift.
 */
class LinkCountModel extends Base
{
    const STUCK_DAYS = 30;

    private function marks(array $ids)
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /**
     * Narrows the tasks to one user's, for the dashboard pages. $mine is null (every task in the projects),
     * array('owner' => id) (tasks assigned to the user), array('tasks' => id) (what My tasks lists: tasks the user owns or has a subtask in) or array('subtasks' => id)
     * (what My subtasks lists: tasks with an open subtask assigned to the user). Returns array(sql, params), with the
     * sql starting with " AND " or empty. The tasks table must be aliased "t".
     */
    private function mine($mine)
    {
        if (empty($mine)) {
            return array('', array());
        }

        if (isset($mine['owner'])) {
            return array(' AND t.owner_id = ?', array((int) $mine['owner']));
        }

        if (isset($mine['subtasks'])) {
            return array(' AND EXISTS (SELECT 1 FROM subtasks s WHERE s.task_id = t.id AND s.user_id = ? AND s.status < 2)', array((int) $mine['subtasks']));
        }

        return array(' AND (t.owner_id = ? OR EXISTS (SELECT 1 FROM subtasks s WHERE s.task_id = t.id AND s.user_id = ?))', array((int) $mine['tasks'], (int) $mine['tasks']));
    }

    /**
     * Counts for the BigBoard links on the dashboard pages: overdue, today, soon, unowned, blocked, stuck, mine.
     * Open tasks only, narrowed by $mine. "Mine" is the tasks assigned to $userId.
     */
    public function linkCounts(array $ids, $soonDays, $userId, $mine = null)
    {
        $out = array('overdue' => 0, 'today' => 0, 'soon' => 0, 'unowned' => 0, 'blocked' => 0, 'stuck' => 0, 'mine' => 0);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (empty($ids)) {
            return $out;
        }

        $now = time();
        $extra = $this->mine($mine);
        $moved = '(CASE WHEN t.date_moved > 0 THEN t.date_moved ELSE t.date_creation END)';
        $sql = 'SELECT
                    SUM(CASE WHEN t.date_due > 0 AND t.date_due < ? THEN 1 ELSE 0 END) AS n_overdue,
                    SUM(CASE WHEN t.date_due >= ? AND t.date_due < ? THEN 1 ELSE 0 END) AS n_today,
                    SUM(CASE WHEN t.date_due >= ? AND t.date_due <= ? THEN 1 ELSE 0 END) AS n_soon,
                    SUM(CASE WHEN t.owner_id = 0 THEN 1 ELSE 0 END) AS n_unowned,
                    SUM(CASE WHEN '.$moved.' BETWEEN 1 AND ? THEN 1 ELSE 0 END) AS n_stuck,
                    SUM(CASE WHEN t.owner_id = ? THEN 1 ELSE 0 END) AS n_mine
                FROM tasks t WHERE t.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')'.$extra[0];
        $params = array_merge(array($now, strtotime('today'), strtotime('tomorrow'), $now, $now + (int) $soonDays * 86400, $now - self::STUCK_DAYS * 86400, (int) $userId), $ids, $extra[1]);
        $row = $this->db->execute($sql, $params)->fetch(\PDO::FETCH_ASSOC);

        return array('overdue' => (int) $row['n_overdue'], 'today' => (int) $row['n_today'], 'soon' => (int) $row['n_soon'], 'unowned' => (int) $row['n_unowned'], 'blocked' => $this->blockedCount($ids, $mine), 'stuck' => (int) $row['n_stuck'], 'mine' => (int) $row['n_mine']);
    }

    private function blockedCount(array $ids, $mine = null)
    {
        $extra = $this->mine($mine);
        $sql = 'SELECT COUNT(DISTINCT t.id) FROM task_has_links l
                JOIN links k ON k.id = l.link_id
                JOIN tasks t ON t.id = l.task_id
                JOIN tasks b ON b.id = l.opposite_task_id
                WHERE k.label = ? AND t.is_active = 1 AND b.is_active = 1 AND t.project_id IN ('.$this->marks($ids).')'.$extra[0];

        return (int) $this->db->execute($sql, array_merge(array('is blocked by'), $ids, $extra[1]))->fetchColumn();
    }
}
