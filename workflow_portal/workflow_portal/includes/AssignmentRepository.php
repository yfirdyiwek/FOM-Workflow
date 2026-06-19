<?php
class AssignmentRepository
{
    public function __construct(private PDO $pdo) {}

    // ── Shared visibility filter ───────────────────────────────────────────

    private function visibilityFilter(int $userId, bool $isScMember): array
    {
        $where  = 'WHERE a.work_type = "official"';
        $params = [];
        if (!$isScMember) {
            $where .= ' AND (a.lead_user_id = ? OR a.id IN (
                SELECT assignment_id FROM assignment_supporting_members WHERE user_id = ?
            ))';
            $params = [$userId, $userId];
        }
        return [$where, $params];
    }

    // ── Dashboard counts ──────────────────────────────────────────────────

    public function dashboardCounts(int $userId, bool $isScMember): array
    {
        [$where, $params] = $this->visibilityFilter($userId, $isScMember);

        $count = function (string $extra) use ($where, $params): int {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM assignments a $where $extra");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        };

        return [
            'active'           => $count('AND a.status NOT IN ("completed","archived")'),
            'due_this_week'    => $count('AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND a.status NOT IN ("completed","archived")'),
            'overdue'          => $count('AND a.due_date < CURDATE() AND a.status NOT IN ("completed","archived")'),
            'ready_for_review' => $count('AND a.status = "ready_for_review"'),
        ];
    }

    // ── Dashboard assignment list ─────────────────────────────────────────

    public function listForDashboard(int $userId, bool $isScMember, string $sort, string $dir): array
    {
        [$where, $params] = $this->visibilityFilter($userId, $isScMember);

        $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        $orderMap = [
            'seq'       => 'CASE WHEN a.sort_order IS NOT NULL THEN 0 ELSE 1 END, a.sort_order, a.title',
            // Natural nav order: SC, CC, ARDC, FC
            'committee' => 'CASE c.short_code WHEN "SC" THEN 1 WHEN "CC" THEN 2 WHEN "ARDC" THEN 3 WHEN "FC" THEN 4 ELSE 99 END',
            'title'     => 'a.title',
            'lead'      => 'u.display_name',
            'support'   => 'support_count',
            'subtasks'  => 'total_subtasks',
            'priority'  => 'FIELD(a.priority, "urgent", "high", "medium", "low")',
            'status'    => 'a.status',
            'due_date'  => 'a.due_date',
            'updated'   => 'a.updated_at',
        ];

        if (!isset($orderMap[$sort])) {
            $sort = 'updated';
        }

        $orderExpr      = $orderMap[$sort];
        $orderDir       = $sort === 'priority' ? ($dir === 'asc' ? 'DESC' : 'ASC') : strtoupper($dir);
        $secondaryOrder = '';
        if ($sort === 'committee') {
            $secondaryOrder = ', c.short_code ' . strtoupper($dir) . ', CASE WHEN a.sort_order IS NOT NULL THEN 0 ELSE 1 END ASC, a.sort_order ASC, a.title ASC';
        }

        $stmt = $this->pdo->prepare("SELECT a.*, c.name AS committee_name, c.short_code, u.display_name AS lead_name,
                COALESCE(sm.support_count, 0) AS support_count,
                COALESCE(st.total_subtasks, 0) AS total_subtasks,
                COALESCE(st.completed_subtasks, 0) AS completed_subtasks
            FROM assignments a
            JOIN committees c ON c.id = a.assigned_committee_id
            LEFT JOIN users u ON u.id = a.lead_user_id
            LEFT JOIN (SELECT assignment_id, COUNT(*) AS support_count FROM assignment_supporting_members GROUP BY assignment_id) sm ON sm.assignment_id = a.id
            LEFT JOIN (
                SELECT assignment_id,
                       COUNT(*) AS total_subtasks,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_subtasks
                FROM assignment_subtasks
                GROUP BY assignment_id
            ) st ON st.assignment_id = a.id
            $where
            ORDER BY $orderExpr $orderDir$secondaryOrder, a.updated_at DESC, a.id DESC
            LIMIT 12");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ── Create assignment ─────────────────────────────────────────────────

    public function create(array $fields, int $createdByUserId): int
    {
        $this->pdo->prepare('INSERT INTO assignments
                (title, short_description, full_description, assigned_committee_id, lead_user_id,
                 priority, status, origin_source, work_type, date_assigned, due_date,
                 created_by_user_id, updated_by_user_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "official", ?, ?, ?, ?, NOW(), NOW())')
            ->execute([
                $fields['title'],
                $fields['short_description'],
                $fields['full_description'],
                (int) $fields['assigned_committee_id'],
                (int) $fields['lead_user_id'],
                $fields['priority'],
                $fields['status'],
                $fields['origin_source'],
                $fields['date_assigned'],
                $fields['due_date'],
                $createdByUserId,
                $createdByUserId,
            ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ── Lookup helpers ────────────────────────────────────────────────────

    public function listActiveCommittees(): array
    {
        return $this->pdo
            ->query('SELECT id, name, short_code FROM committees WHERE is_active = 1 ORDER BY name')
            ->fetchAll();
    }

    public function listEligibleLeads(): array
    {
        return $this->pdo
            ->query('SELECT u.id, u.display_name, u.first_name, u.last_name, m.committee_id, c.short_code
                FROM users u
                JOIN user_committee_memberships m ON m.user_id = u.id
                JOIN committees c ON c.id = m.committee_id
                WHERE u.is_active = 1 AND u.role_level != "superadmin"
                ORDER BY u.display_name, u.last_name, u.first_name')
            ->fetchAll();
    }

    public function recentActivity(int $limit = 12): array
    {
        $stmt = $this->pdo->prepare('SELECT al.*, u.display_name
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC
            LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
