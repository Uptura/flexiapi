<?php
// src/Custom/UserMethods.php

return [
    'getTopUsers' => function ($db, $params) {
        $limit = (int)($params['limit'] ?? 5);
        $sql   = "SELECT id, username, email 
                  FROM users 
                  ORDER BY created_at DESC 
                  LIMIT {$limit}";
        return $db->query($sql); // ✅ returns array already
    },

    'activeUserCount' => function ($db) {
        $sql = "SELECT COUNT(*) AS total FROM users WHERE status = 'active'";
        $rows = $db->query($sql); // ✅ returns array
        return $rows[0] ?? ['total' => 0];
    }
];
