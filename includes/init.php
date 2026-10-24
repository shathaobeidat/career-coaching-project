<?php

require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AREAS = [
    'admin' => [
        'role'  => 'admin',
        'title' => 'CareerPath Admin',
        'home'  => 'dashboard.php',
        'wide'  => true,
        'nav'   => [
            'dashboard' => ['dashboard.php', 'Dashboard'],
            'coaches'   => ['coaches.php',   'Coaches'],
            'users'     => ['users.php',     'Users'],
            'bookings'  => ['bookings.php',  'Bookings'],
            'services'  => ['services.php',  'Services'],
            'reviews'   => ['reviews.php',   'Reviews'],
            'profile'   => ['profile.php',   'My account'],
        ],
    ],
    'coach' => [
        'role'  => 'mentor',
        'title' => 'CareerPath Coach',
        'home'  => 'coach-dashboard.php',
        'wide'  => false,
        'nav'   => [
            'dashboard'    => ['coach-dashboard.php', 'Dashboard'],
            'bookings'     => ['bookings.php',        'Bookings'],
            'services'     => ['services.php',        'Services'],
            'availability' => ['availability.php',    'Availability'],
            'reviews'      => ['reviews.php',         'Reviews'],
            'profile'      => ['coach-profile.php',   'Profile'],
        ],
    ],
    'user' => [
        'role'  => 'mentee',
        'title' => 'CareerPath',
        'home'  => 'dashboard.php',
        'wide'  => false,
        'nav'   => [
            'dashboard'  => ['dashboard.php',  'Dashboard'],
            'sessions'   => ['sessions.php',   'Sessions'],
            'find-coach' => ['find-coach.php', 'Find a coach'],
            'profile'    => ['profile.php',    'Profile'],
        ],
    ],
];

$area = basename(dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
if (!isset($AREAS[$area])) {
    http_response_code(500);
    exit('includes/init.php must be used from the admin, coach or user folder.');
}
$areaConfig = $AREAS[$area];

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== $areaConfig['role']) {
    header('Location: ../auth/login.php');
    exit();
}

$db       = new DB_con();
$userId   = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';


$profile = $areaConfig['role'] === 'mentor' ? $db->getCoachProfile($userId) : false;
$coachId = $profile ? (int) $profile['id'] : 0;
