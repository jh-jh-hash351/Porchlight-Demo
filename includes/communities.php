<?php
/**
 * Community helpers: open communities and admin-approved communities.
 */

function ensure_communities_schema(PDO $pdo): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS communities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL,
        description TEXT NOT NULL,
        join_policy ENUM('open', 'approval') NOT NULL DEFAULT 'open',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_community_name (name),
        CONSTRAINT fk_communities_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS community_members (
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
        status ENUM('active', 'pending') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (community_id, user_id),
        INDEX idx_cm_user (user_id, status),
        INDEX idx_cm_status (community_id, status),
        CONSTRAINT fk_cm_community FOREIGN KEY (community_id) REFERENCES communities(id) ON DELETE CASCADE,
        CONSTRAINT fk_cm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensured = true;
}

function community_membership(PDO $pdo, int $communityId, int $userId): ?array {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM community_members WHERE community_id = ? AND user_id = ?');
    $stmt->execute([$communityId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function is_community_admin(PDO $pdo, int $communityId, int $userId): bool {
    $membership = community_membership($pdo, $communityId, $userId);
    return $membership
        && $membership['role'] === 'admin'
        && $membership['status'] === 'active';
}

/**
 * Returns [communityId => [admin rows]] for the given community IDs.
 */
function community_admins_map(PDO $pdo, array $communityIds): array {
    ensure_communities_schema($pdo);

    $ids = array_values(array_unique(array_map('intval', $communityIds)));
    $map = [];
    if (!$ids) {
        return $map;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT m.community_id, u.id, u.username, u.profile_pic
        FROM community_members m
        JOIN users u ON u.id = m.user_id
        WHERE m.community_id IN ($placeholders)
          AND m.role = 'admin'
          AND m.status = 'active'
          AND u.status = 'Active'
        ORDER BY u.username ASC
    ");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $row) {
        $map[(int)$row['community_id']][] = $row;
    }
    return $map;
}

/**
 * Lists communities with member counts, admins, and the viewer's status.
 * viewer_status: 'none' | 'pending' | 'member' | 'admin'
 */
function get_communities(PDO $pdo, ?int $viewerId): array {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.description, c.join_policy, c.created_by, c.created_at,
               (SELECT COUNT(*) FROM community_members m
                 WHERE m.community_id = c.id AND m.status = 'active') AS member_count,
               (SELECT COUNT(*) FROM community_members m
                 WHERE m.community_id = c.id AND m.status = 'pending') AS pending_count,
               mine.role AS my_role,
               mine.status AS my_status
        FROM communities c
        LEFT JOIN community_members mine
          ON mine.community_id = c.id AND mine.user_id = ?
        ORDER BY c.created_at DESC, c.id DESC
    ");
    $stmt->execute([$viewerId ?? 0]);
    $rows = $stmt->fetchAll();

    $adminMap = community_admins_map($pdo, array_column($rows, 'id'));

    foreach ($rows as &$row) {
        $row['admins'] = $adminMap[(int)$row['id']] ?? [];
        $row['member_count'] = (int)$row['member_count'];
        $row['pending_count'] = (int)$row['pending_count'];

        if ($row['my_status'] === 'active' && $row['my_role'] === 'admin') {
            $row['viewer_status'] = 'admin';
        } elseif ($row['my_status'] === 'active') {
            $row['viewer_status'] = 'member';
        } elseif ($row['my_status'] === 'pending') {
            $row['viewer_status'] = 'pending';
        } else {
            $row['viewer_status'] = 'none';
        }
    }
    unset($row);

    return $rows;
}

function create_community(PDO $pdo, int $creatorId, string $name, string $description, string $policy): array {
    ensure_communities_schema($pdo);

    $name = trim(preg_replace('/\s+/', ' ', $name));
    $description = trim($description);
    $policy = $policy === 'approval' ? 'approval' : 'open';

    if (strlen($name) < 3 || strlen($name) > 80) {
        return ['error' => 'Community name must be between 3 and 80 characters.'];
    }
    if ($description === '') {
        return ['error' => 'Add a short description so people know what the community is about.'];
    }
    if (strlen($description) > 1000) {
        return ['error' => 'Keep the description under 1000 characters.'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO communities (name, description, join_policy, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $description, $policy, $creatorId]);
        $communityId = (int)$pdo->lastInsertId();

        $stmtAdmin = $pdo->prepare("
            INSERT INTO community_members (community_id, user_id, role, status)
            VALUES (?, ?, 'admin', 'active')
        ");
        $stmtAdmin->execute([$communityId, $creatorId]);

        $pdo->commit();
        return ['id' => $communityId];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return ['error' => 'That community name is already taken.'];
        }
        return ['error' => 'Could not create the community right now.'];
    }
}

/**
 * Returns: 'active' | 'pending' | 'member' | 'admin' | 'missing'
 */
function join_community(PDO $pdo, int $userId, int $communityId): string {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, join_policy FROM communities WHERE id = ?');
    $stmt->execute([$communityId]);
    $community = $stmt->fetch();
    if (!$community) {
        return 'missing';
    }

    $membership = community_membership($pdo, $communityId, $userId);
    if ($membership) {
        if ($membership['status'] === 'pending') {
            return 'pending';
        }
        return $membership['role'] === 'admin' ? 'admin' : 'member';
    }

    $status = $community['join_policy'] === 'approval' ? 'pending' : 'active';
    $stmtJoin = $pdo->prepare("
        INSERT INTO community_members (community_id, user_id, role, status)
        VALUES (?, ?, 'member', ?)
    ");
    $stmtJoin->execute([$communityId, $userId, $status]);

    return $status;
}

/**
 * Leaves a community or cancels a pending request.
 * Returns the new viewer status: 'none' or 'admin' (admins cannot leave).
 */
function leave_community(PDO $pdo, int $userId, int $communityId): string {
    $membership = community_membership($pdo, $communityId, $userId);
    if (!$membership) {
        return 'none';
    }
    if ($membership['role'] === 'admin' && $membership['status'] === 'active') {
        return 'admin';
    }

    $stmt = $pdo->prepare('DELETE FROM community_members WHERE community_id = ? AND user_id = ?');
    $stmt->execute([$communityId, $userId]);

    return 'none';
}

function get_pending_requests(PDO $pdo, int $communityId): array {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT m.user_id AS id, m.created_at AS requested_at,
               u.username, u.profile_pic, u.created_at AS member_since
        FROM community_members m
        JOIN users u ON u.id = m.user_id
        WHERE m.community_id = ?
          AND m.status = 'pending'
          AND u.status = 'Active'
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$communityId]);
    return $stmt->fetchAll();
}

/**
 * Returns: 'member' | 'none' | 'forbidden'
 */
function respond_to_community_request(PDO $pdo, int $communityId, int $adminId, int $userId, bool $approve): string {
    if (!is_community_admin($pdo, $communityId, $adminId)) {
        return 'forbidden';
    }

    $membership = community_membership($pdo, $communityId, $userId);
    if (!$membership || $membership['status'] !== 'pending') {
        return 'none';
    }

    if ($approve) {
        $stmt = $pdo->prepare("
            UPDATE community_members SET status = 'active'
            WHERE community_id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$communityId, $userId]);
        return 'member';
    }

    $stmt = $pdo->prepare("
        DELETE FROM community_members
        WHERE community_id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$communityId, $userId]);
    return 'none';
}

function community_policy_label(string $policy): string {
    return $policy === 'approval' ? 'Approval required' : 'Open to join';
}

function get_community(PDO $pdo, int $communityId): ?array {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM community_members m
                 WHERE m.community_id = c.id AND m.status = 'active') AS member_count,
               (SELECT COUNT(*) FROM community_members m
                 WHERE m.community_id = c.id AND m.status = 'pending') AS pending_count
        FROM communities c
        WHERE c.id = ?
    ");
    $stmt->execute([$communityId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }
    $row['member_count'] = (int)$row['member_count'];
    $row['pending_count'] = (int)$row['pending_count'];
    return $row;
}

function get_community_members(PDO $pdo, int $communityId): array {
    ensure_communities_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT m.role, m.created_at AS joined_at,
               u.id, u.username, u.profile_pic
        FROM community_members m
        JOIN users u ON u.id = m.user_id
        WHERE m.community_id = ?
          AND m.status = 'active'
          AND u.status = 'Active'
        ORDER BY (m.role = 'admin') DESC, u.username ASC
    ");
    $stmt->execute([$communityId]);
    return $stmt->fetchAll();
}
?>
