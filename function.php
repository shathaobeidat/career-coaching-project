<?php
class DB_con {
    private $dbh;

    const BOOKING_TRANSITIONS = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    const CANCELLATION_HOURS = 24;

    public function __construct() {
        $host = 'localhost';
        $db   = 'career_coaching';
        $user = 'root';
        $pass = '';

        try {
         
            $this->dbh = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }



    public function register($name, $email, $password, $role) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $inst = $this->dbh->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        return $inst->execute([$name, $email, $hashedPassword, $role]);
    }

    public function login($email, $password) {
        $stmt = $this->dbh->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            return $user; 
        }
        return false; 
    }

    public function getUserBookings($userId) {
        $stmt = $this->dbh->prepare("  SELECT bookings.*, services.name AS service_title, services.duration_minutes,  availability.start_at,  users.name AS coach_name  FROM bookings 
            JOIN services ON bookings.service_id = services.id 
            JOIN availability ON bookings.availability_id = availability.id 
            JOIN coach_profiles ON services.coach_id = coach_profiles.id 
            JOIN users ON coach_profiles.user_id = users.id 
            WHERE bookings.customer_id = ? 
            ORDER BY availability.start_at ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    private function result($ok, array $errors = [], array $extra = []) {
        return array_merge(['ok' => $ok, 'errors' => $errors], $extra);
    }


    public function getUserById($userId) {
        $stmt = $this->dbh->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUserAccount($userId, $name, $email) {
        $name  = trim($name);
        $email = trim($email);
        $errors = [];

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Name is required (max 100 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($errors) {
            return $this->result(false, $errors);
        }

        $stmt = $this->dbh->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            return $this->result(false, ['This email is already used by another account.']);
        }

        $stmt = $this->dbh->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $email, $userId]);
        return $this->result(true);
    }

    public function changePassword($userId, $currentPassword, $newPassword) {
        $stmt = $this->dbh->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($currentPassword, $hash)) {
            return $this->result(false, ['Your current password is incorrect.']);
        }
        if (strlen($newPassword) < 8) {
            return $this->result(false, ['The new password must be at least 8 characters.']);
        }

        $stmt = $this->dbh->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        return $this->result(true);
    }

    
    public function getCoachProfile($userId) {
        $stmt = $this->dbh->prepare(
            "SELECT cp.*, u.name, u.email
             FROM coach_profiles cp
             JOIN users u ON u.id = cp.user_id
             WHERE cp.user_id = ?"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

   
    public function saveCoachProfile($userId, array $data, $imagePath = null) {
        $title      = trim($data['professional_title'] ?? '');
        $bio        = trim($data['bio'] ?? '');
        $years      = filter_var($data['experience_years'] ?? 0, FILTER_VALIDATE_INT);
        $education  = trim($data['education'] ?? '');
        $certs      = trim($data['certifications'] ?? '');
        $linkedin   = trim($data['linkedin_url'] ?? '');
        $errors = [];

        if ($title === '' || mb_strlen($title) > 100) {
            $errors[] = 'Professional title is required (max 100 characters).';
        }
        if ($bio === '' || mb_strlen($bio) > 5000) {
            $errors[] = 'Bio is required (max 5000 characters).';
        }
        if ($years === false || $years < 0 || $years > 60) {
            $errors[] = 'Years of experience must be a number between 0 and 60.';
        }
        if (mb_strlen($education) > 255) {
            $errors[] = 'Education is too long (max 255 characters).';
        }
        if (mb_strlen($certs) > 2000) {
            $errors[] = 'Certifications are too long (max 2000 characters).';
        }
        if ($linkedin !== '') {
            $scheme = strtolower((string) parse_url($linkedin, PHP_URL_SCHEME));
            if (!filter_var($linkedin, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || mb_strlen($linkedin) > 255) {
                $errors[] = 'LinkedIn URL must be a valid http(s) link.';
            }
        }
        if ($errors) {
            return $this->result(false, $errors);
        }

        $education = $education === '' ? null : $education;
        $certs     = $certs === '' ? null : $certs;
        $linkedin  = $linkedin === '' ? null : $linkedin;

        $stmt = $this->dbh->prepare("SELECT id, status, profile_image FROM coach_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $stmt = $this->dbh->prepare(
                "INSERT INTO coach_profiles
                 (user_id, professional_title, bio, experience_years, education, certifications, profile_image, linkedin_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $title, $bio, $years, $education, $certs, $imagePath, $linkedin]);
            return $this->result(true, [], ['created' => true]);
        }

        $newStatus = $existing['status'] === 'rejected' ? 'pending' : $existing['status'];
        $image     = $imagePath !== null ? $imagePath : $existing['profile_image'];

        $stmt = $this->dbh->prepare(
            "UPDATE coach_profiles
             SET professional_title = ?, bio = ?, experience_years = ?, education = ?, certifications = ?,
                 profile_image = ?, linkedin_url = ?, status = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$title, $bio, $years, $education, $certs, $image, $linkedin, $newStatus, $existing['id']]);
        return $this->result(true, [], ['created' => false, 'resubmitted' => $existing['status'] === 'rejected']);
    }

    
    public function uploadProfileImage(array $file) {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return $this->result(true, [], ['path' => null]);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->result(false, ['The image could not be uploaded. Please try again.'], ['path' => null]);
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            return $this->result(false, ['The image must be 2 MB or smaller.'], ['path' => null]);
        }

        $info = @getimagesize($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($allowed[$info['mime']])) {
            return $this->result(false, ['Only JPG, PNG or WEBP images are allowed.'], ['path' => null]);
        }

        $dir = __DIR__ . '/uploads/coaches';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return $this->result(false, ['Upload folder could not be created.'], ['path' => null]);
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$info['mime']];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return $this->result(false, ['The image could not be saved.'], ['path' => null]);
        }
        return $this->result(true, [], ['path' => 'uploads/coaches/' . $filename]);
    }

    public function deleteProfileImage($path) {
        if (!$path || strpos($path, 'uploads/coaches/') !== 0) {
            return;
        }
        $full = __DIR__ . '/uploads/coaches/' . basename($path);
        if (is_file($full)) {
            @unlink($full);
        }
    }

  
    private function validateService($name, $description, $duration, $price) {
        $errors = [];
        $name = trim($name);
        $d = filter_var($duration, FILTER_VALIDATE_INT);

        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Service name is required (max 100 characters).';
        }
        if (mb_strlen(trim($description)) > 2000) {
            $errors[] = 'Description is too long (max 2000 characters).';
        }
        if ($d === false || $d < 15 || $d > 480) {
            $errors[] = 'Duration must be between 15 and 480 minutes.';
        }
        if (!is_numeric($price) || $price < 0 || $price > 99999999.99) {
            $errors[] = 'Price must be a positive number.';
        }
        return $errors;
    }

    public function getCoachServices($coachId) {
        $stmt = $this->dbh->prepare(
            "SELECT s.*, (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS bookings_count
             FROM services s
             WHERE s.coach_id = ?
             ORDER BY s.is_active DESC, s.created_at DESC, s.id DESC"
        );
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getService($serviceId, $coachId) {
        $stmt = $this->dbh->prepare("SELECT * FROM services WHERE id = ? AND coach_id = ?");
        $stmt->execute([$serviceId, $coachId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addService($coachId, $name, $description, $duration, $price, $isActive = 1) {
        $errors = $this->validateService($name, $description, $duration, $price);
        if ($errors) {
            return $this->result(false, $errors);
        }
        $description = trim($description);
        $stmt = $this->dbh->prepare(
            "INSERT INTO services (coach_id, name, description, duration_minutes, price, is_active)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $coachId, trim($name), $description === '' ? null : $description,
            (int) $duration, round((float) $price, 2), $isActive ? 1 : 0,
        ]);
        return $this->result(true, [], ['id' => (int) $this->dbh->lastInsertId()]);
    }

    public function updateService($serviceId, $coachId, $name, $description, $duration, $price, $isActive = 1) {
        if (!$this->getService($serviceId, $coachId)) {
            return $this->result(false, ['Service not found.']);
        }
        $errors = $this->validateService($name, $description, $duration, $price);
        if ($errors) {
            return $this->result(false, $errors);
        }
        $description = trim($description);
        $stmt = $this->dbh->prepare(
            "UPDATE services
             SET name = ?, description = ?, duration_minutes = ?, price = ?, is_active = ?, updated_at = NOW()
             WHERE id = ? AND coach_id = ?"
        );
        $stmt->execute([
            trim($name), $description === '' ? null : $description,
            (int) $duration, round((float) $price, 2), $isActive ? 1 : 0, $serviceId, $coachId,
        ]);
        return $this->result(true);
    }

    public function setServiceActive($serviceId, $coachId, $active) {
        $stmt = $this->dbh->prepare("UPDATE services SET is_active = ?, updated_at = NOW() WHERE id = ? AND coach_id = ?");
        $stmt->execute([$active ? 1 : 0, $serviceId, $coachId]);
        return $this->result($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? [] : ['Service not found.']);
    }

    public function deleteService($serviceId, $coachId) {
        if (!$this->getService($serviceId, $coachId)) {
            return $this->result(false, ['Service not found.']);
        }
        $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM bookings WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return $this->result(false, ['This service already has bookings, so it cannot be deleted. Deactivate it instead.']);
        }
        $stmt = $this->dbh->prepare("DELETE FROM services WHERE id = ? AND coach_id = ?");
        $stmt->execute([$serviceId, $coachId]);
        return $this->result(true);
    }

   
    public function getCoachAvailability($coachId, $includePast = false) {
        $sql = "SELECT a.*, b.id AS booking_id, b.status AS booking_status, u.name AS customer_name
                FROM availability a
                LEFT JOIN bookings b ON b.availability_id = a.id AND b.status IN ('pending', 'confirmed')
                LEFT JOIN users u ON u.id = b.customer_id
                WHERE a.coach_profile_id = ?";
        $params = [$coachId];
        if (!$includePast) {
            $sql .= " AND a.end_at >= ?";
            $params[] = date('Y-m-d H:i:s');
        }
        $sql .= " ORDER BY a.start_at ASC";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

   
    public function addAvailability($coachId, $startAt, $endAt, $slotMinutes = 0) {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $startAt);
        $end   = DateTimeImmutable::createFromFormat('Y-m-d H:i', $endAt);
        $slotMinutes = (int) $slotMinutes;

        if (!$start || !$end) {
            return $this->result(false, ['Please choose a valid date and time.']);
        }
        if ($start <= new DateTimeImmutable('now')) {
            return $this->result(false, ['The start time must be in the future.']);
        }
        if ($end <= $start) {
            return $this->result(false, ['The end time must be after the start time.']);
        }
        if (($end->getTimestamp() - $start->getTimestamp()) > 12 * 3600) {
            return $this->result(false, ['A single block cannot be longer than 12 hours.']);
        }
        if ($slotMinutes !== 0 && ($slotMinutes < 15 || $slotMinutes > 240)) {
            return $this->result(false, ['Slot length must be between 15 and 240 minutes.']);
        }

        $slots = [];
        if ($slotMinutes === 0) {
            $slots[] = [$start, $end];
        } else {
            $step = new DateInterval('PT' . $slotMinutes . 'M');
            for ($cursor = $start; $cursor->add($step) <= $end; $cursor = $cursor->add($step)) {
                $slots[] = [$cursor, $cursor->add($step)];
            }
            if (!$slots) {
                return $this->result(false, ['The time range is shorter than one slot.']);
            }
        }

        $overlap = $this->dbh->prepare(
            "SELECT start_at FROM availability
             WHERE coach_profile_id = ? AND start_at < ? AND end_at > ? LIMIT 1"
        );
        $insert = $this->dbh->prepare(
            "INSERT INTO availability (coach_profile_id, start_at, end_at) VALUES (?, ?, ?)"
        );

        try {
            $this->dbh->beginTransaction();
            foreach ($slots as [$s, $e]) {
                $overlap->execute([$coachId, $e->format('Y-m-d H:i:s'), $s->format('Y-m-d H:i:s')]);
                if ($row = $overlap->fetch(PDO::FETCH_ASSOC)) {
                    $this->dbh->rollBack();
                    return $this->result(false, [
                        'Overlaps an existing slot at ' . date('D d M, g:i A', strtotime($row['start_at'])) . '. Nothing was added.'
                    ]);
                }
                $insert->execute([$coachId, $s->format('Y-m-d H:i:s'), $e->format('Y-m-d H:i:s')]);
            }
            $this->dbh->commit();
        } catch (Throwable $ex) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return $this->result(false, ['Could not save the availability. Please try again.']);
        }
        return $this->result(true, [], ['created' => count($slots)]);
    }

  
    public function toggleAvailability($availabilityId, $coachId) {
        $stmt = $this->dbh->prepare(
            "UPDATE availability
             SET is_available = 1 - is_available
             WHERE id = ? AND coach_profile_id = ?
               AND NOT EXISTS (SELECT 1 FROM bookings b
                               WHERE b.availability_id = availability.id AND b.status IN ('pending', 'confirmed'))"
        );
        $stmt->execute([$availabilityId, $coachId]);
        return $this->result($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? [] : ['This slot is booked or no longer exists.']);
    }

    public function deleteAvailability($availabilityId, $coachId) {
        $stmt = $this->dbh->prepare(
            "DELETE FROM availability
             WHERE id = ? AND coach_profile_id = ?
               AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.availability_id = availability.id)"
        );
        $stmt->execute([$availabilityId, $coachId]);
        return $this->result($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? [] : ['This slot has bookings (hide it instead) or no longer exists.']);
    }

    
    private function bookingSelect() {
        return "SELECT b.*, s.name AS service_name, s.duration_minutes, s.price,
                       a.start_at, a.end_at,
                       u.name AS customer_name, u.email AS customer_email
                FROM bookings b
                JOIN services s     ON b.service_id = s.id
                JOIN availability a ON b.availability_id = a.id
                JOIN users u        ON b.customer_id = u.id";
    }


    public function getCoachBookings($coachId, $status = null) {
        $sql = $this->bookingSelect() . " WHERE s.coach_id = ?";
        $params = [$coachId];
        if ($status !== null) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY a.start_at DESC";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCoachUpcomingSessions($coachId, $limit = 5) {
        $stmt = $this->dbh->prepare(
            $this->bookingSelect() . " WHERE s.coach_id = ? AND b.status = 'confirmed' AND a.end_at >= ?
             ORDER BY a.start_at ASC LIMIT " . (int) $limit
        );
        $stmt->execute([$coachId, date('Y-m-d H:i:s')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateBookingStatus($bookingId, $coachId, $newStatus) {
        return $this->changeBookingStatus($bookingId, $newStatus, 'coach', $coachId);
    }

    public function adminUpdateBookingStatus($bookingId, $newStatus) {
        return $this->changeBookingStatus($bookingId, $newStatus, 'admin');
    }

    
    public function cancelMenteeBooking($bookingId, $customerId) {
        return $this->changeBookingStatus($bookingId, 'cancelled', 'customer', $customerId);
    }

    private function changeBookingStatus($bookingId, $newStatus, $actor, $actorId = null) {
        if (!in_array($newStatus, ['confirmed', 'completed', 'cancelled'], true)) {
            return $this->result(false, ['Invalid action.']);
        }
        if ($actor === 'customer' && $newStatus !== 'cancelled') {
            return $this->result(false, ['Invalid action.']);
        }

        try {
            $this->dbh->beginTransaction();

            $sql = "SELECT b.id, b.status, b.availability_id, a.start_at
                    FROM bookings b
                    JOIN services s     ON b.service_id = s.id
                    JOIN availability a ON b.availability_id = a.id
                    WHERE b.id = ?";
            $params = [$bookingId];
            if ($actor === 'coach') {
                $sql .= " AND s.coach_id = ?";
                $params[] = $actorId;
            } elseif ($actor === 'customer') {
                $sql .= " AND b.customer_id = ?";
                $params[] = $actorId;
            }
            $stmt = $this->dbh->prepare($sql . " FOR UPDATE");
            $stmt->execute($params);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $this->dbh->rollBack();
                return $this->result(false, ['Booking not found.']);
            }
            if (!in_array($newStatus, self::BOOKING_TRANSITIONS[$booking['status']], true)) {
                $this->dbh->rollBack();
                return $this->result(false, ['A ' . $booking['status'] . ' booking cannot be changed to ' . $newStatus . '.']);
            }

            if ($actor === 'customer' && !self::canCustomerModify($booking['status'], $booking['start_at'])) {
                $this->dbh->rollBack();
                return $this->result(false, [self::deadlineMessage()]);
            }

            $now = date('Y-m-d H:i:s');
            if ($newStatus === 'completed' && $booking['start_at'] > $now) {
                $this->dbh->rollBack();
                return $this->result(false, ['A session can only be marked completed after it has started.']);
            }

            $stmt = $this->dbh->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $bookingId]);

            if ($newStatus === 'cancelled' && $booking['start_at'] > $now) {
                $stmt = $this->dbh->prepare("UPDATE availability SET is_available = 1 WHERE id = ?");
                $stmt->execute([$booking['availability_id']]);
            } elseif ($newStatus === 'confirmed') {
                $stmt = $this->dbh->prepare("UPDATE availability SET is_available = 0 WHERE id = ?");
                $stmt->execute([$booking['availability_id']]);
            }

            $this->dbh->commit();
            return $this->result(true, [], ['status' => $newStatus]);
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return $this->result(false, ['Could not update the booking. Please try again.']);
        }
    }

  

    public function getCoachStats($coachId) {
        $now = date('Y-m-d H:i:s');
        $one = function ($sql, $params) {
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        };

        $base = "FROM bookings b JOIN services s ON b.service_id = s.id JOIN availability a ON b.availability_id = a.id WHERE s.coach_id = ?";

        $rating = $this->getCoachRatingSummary($coachId);

        return [
            'pending_requests'   => (int) $one("SELECT COUNT(*) $base AND b.status = 'pending'", [$coachId]),
            'upcoming_sessions'  => (int) $one("SELECT COUNT(*) $base AND b.status = 'confirmed' AND a.end_at >= ?", [$coachId, $now]),
            'completed_sessions' => (int) $one("SELECT COUNT(*) $base AND b.status = 'completed'", [$coachId]),
            'earnings'           => (float) $one("SELECT COALESCE(SUM(s.price), 0) $base AND b.status = 'completed'", [$coachId]),
            'active_services'    => (int) $one("SELECT COUNT(*) FROM services WHERE coach_id = ? AND is_active = 1", [$coachId]),
            'open_slots'         => (int) $one(
                "SELECT COUNT(*) FROM availability a
                 WHERE a.coach_profile_id = ? AND a.is_available = 1 AND a.start_at >= ?
                   AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.availability_id = a.id AND b.status IN ('pending','confirmed'))",
                [$coachId, $now]
            ),
            'avg_rating'         => $rating['average'],
            'reviews_count'      => $rating['total'],
        ];
    }

    public function getCoachReviews($coachId) {
        $stmt = $this->dbh->prepare(
            "SELECT r.*, u.name AS customer_name, s.name AS service_name, a.start_at
             FROM reviews r
             JOIN bookings b     ON r.booking_id = b.id
             JOIN services s     ON b.service_id = s.id
             JOIN availability a ON b.availability_id = a.id
             JOIN users u        ON b.customer_id = u.id
             WHERE s.coach_id = ?
             ORDER BY r.created_at DESC, r.id DESC"
        );
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getCoachRatingSummary($coachId) {
        $stmt = $this->dbh->prepare(
            "SELECT r.rating, COUNT(*) AS n
             FROM reviews r
             JOIN bookings b ON r.booking_id = b.id
             JOIN services s ON b.service_id = s.id
             WHERE s.coach_id = ?
             GROUP BY r.rating"
        );
        $stmt->execute([$coachId]);

        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $sum = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rating = (int) $row['rating'];
            if (isset($dist[$rating])) {
                $dist[$rating] = (int) $row['n'];
                $total += (int) $row['n'];
                $sum += $rating * (int) $row['n'];
            }
        }
        return [
            'average'      => $total ? round($sum / $total, 1) : null,
            'total'        => $total,
            'distribution' => $dist,
        ];
    }

  
    private function coachCardSelect() {
        return "SELECT cp.id, cp.user_id, cp.professional_title, cp.bio, cp.experience_years, cp.education,
                       cp.certifications, cp.profile_image, cp.linkedin_url, u.name,
                       (SELECT MIN(s.price) FROM services s WHERE s.coach_id = cp.id AND s.is_active = 1) AS min_price,
                       (SELECT COUNT(*) FROM services s WHERE s.coach_id = cp.id AND s.is_active = 1) AS services_count,
                       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r
                          JOIN bookings b ON r.booking_id = b.id JOIN services s ON b.service_id = s.id
                          WHERE s.coach_id = cp.id) AS avg_rating,
                       (SELECT COUNT(*) FROM reviews r
                          JOIN bookings b ON r.booking_id = b.id JOIN services s ON b.service_id = s.id
                          WHERE s.coach_id = cp.id) AS reviews_count
                FROM coach_profiles cp
                JOIN users u ON u.id = cp.user_id
                WHERE cp.status = 'approved'
                  AND EXISTS (SELECT 1 FROM services s WHERE s.coach_id = cp.id AND s.is_active = 1)";
    }


    public function getApprovedCoaches($search = '') {
        $sql = $this->coachCardSelect();
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= " AND (u.name LIKE ? OR cp.professional_title LIKE ? OR cp.bio LIKE ?)";
            $params = [$like, $like, $like];
        }
        $stmt = $this->dbh->prepare($sql . " ORDER BY avg_rating IS NULL, avg_rating DESC, cp.experience_years DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getApprovedCoach($coachId) {
        $stmt = $this->dbh->prepare($this->coachCardSelect() . " AND cp.id = ?");
        $stmt->execute([$coachId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getActiveServices($coachId) {
        $stmt = $this->dbh->prepare("SELECT * FROM services WHERE coach_id = ? AND is_active = 1 ORDER BY price ASC, name ASC");
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Future, open slots of a coach that no pending/confirmed booking holds. */
    public function getBookableSlots($coachId) {
        $stmt = $this->dbh->prepare(
            "SELECT a.* FROM availability a
             WHERE a.coach_profile_id = ? AND a.is_available = 1 AND a.start_at > ?
               AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.availability_id = a.id AND b.status IN ('pending','confirmed'))
             ORDER BY a.start_at ASC LIMIT 100"
        );
        $stmt->execute([$coachId, date('Y-m-d H:i:s')]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function createBooking($customerId, $serviceId, $availabilityId, $notes = '') {
        $notes = trim($notes);
        if (mb_strlen($notes) > 1000) {
            return $this->result(false, ['Notes are too long (max 1000 characters).']);
        }

        try {
            $this->dbh->beginTransaction();

            $stmt = $this->dbh->prepare(
                "SELECT s.id, s.coach_id, s.is_active, cp.status AS coach_status, cp.user_id AS coach_user_id
                 FROM services s JOIN coach_profiles cp ON cp.id = s.coach_id
                 WHERE s.id = ?"
            );
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->dbh->prepare("SELECT id, coach_profile_id, start_at, is_available FROM availability WHERE id = ? FOR UPDATE");
            $stmt->execute([$availabilityId]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);

            $error = null;
            if (!$service || !$service['is_active'] || $service['coach_status'] !== 'approved') {
                $error = 'This service is not available for booking.';
            } elseif (!$slot || (int) $slot['coach_profile_id'] !== (int) $service['coach_id']) {
                $error = 'Please choose a time slot.';
            } elseif ((int) $service['coach_user_id'] === (int) $customerId) {
                $error = 'You cannot book your own session.';
            } elseif (!$slot['is_available'] || $slot['start_at'] <= date('Y-m-d H:i:s')) {
                $error = 'Sorry, this time slot is no longer available.';
            } else {
                $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM bookings WHERE availability_id = ? AND status IN ('pending','confirmed')");
                $stmt->execute([$availabilityId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $error = 'Sorry, this time slot was just taken.';
                }
            }
            if ($error) {
                $this->dbh->rollBack();
                return $this->result(false, [$error]);
            }

            $stmt = $this->dbh->prepare(
                "INSERT INTO bookings (customer_id, service_id, availability_id, customer_notes) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$customerId, $serviceId, $availabilityId, $notes === '' ? null : $notes]);
            $id = (int) $this->dbh->lastInsertId();

            $stmt = $this->dbh->prepare("UPDATE availability SET is_available = 0 WHERE id = ?");
            $stmt->execute([$availabilityId]);

            $this->dbh->commit();
            return $this->result(true, [], ['id' => $id]);
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return $this->result(false, ['Could not create the booking. Please try again.']);
        }
    }


    public function getMenteeSessions($customerId) {
        $stmt = $this->dbh->prepare(
            "SELECT b.*, s.name AS service_name, s.duration_minutes, s.price, a.start_at, a.end_at,
                    cu.name AS coach_name, cp.id AS coach_id, r.id AS review_id, r.rating AS review_rating
             FROM bookings b
             JOIN services s        ON b.service_id = s.id
             JOIN availability a    ON b.availability_id = a.id
             JOIN coach_profiles cp ON s.coach_id = cp.id
             JOIN users cu          ON cu.id = cp.user_id
             LEFT JOIN reviews r    ON r.booking_id = b.id
             WHERE b.customer_id = ?
             ORDER BY a.start_at DESC"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

   
    public function addReview($bookingId, $customerId, $rating, $comment = '') {
        $rating  = filter_var($rating, FILTER_VALIDATE_INT);
        $comment = trim($comment);
        if ($rating === false || $rating < 1 || $rating > 5) {
            return $this->result(false, ['Please choose a rating from 1 to 5.']);
        }
        if (mb_strlen($comment) > 2000) {
            return $this->result(false, ['The comment is too long (max 2000 characters).']);
        }

        $stmt = $this->dbh->prepare("SELECT status FROM bookings WHERE id = ? AND customer_id = ?");
        $stmt->execute([$bookingId, $customerId]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            return $this->result(false, ['Booking not found.']);
        }
        if ($status !== 'completed') {
            return $this->result(false, ['You can only review a completed session.']);
        }

        $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM reviews WHERE booking_id = ?");
        $stmt->execute([$bookingId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return $this->result(false, ['You already reviewed this session.']);
        }

        $stmt = $this->dbh->prepare("INSERT INTO reviews (booking_id, rating, comment) VALUES (?, ?, ?)");
        $stmt->execute([$bookingId, $rating, $comment === '' ? null : $comment]);
        return $this->result(true);
    }

  
    public static function canCustomerModify($status, $startAt) {
        if ($status === 'pending') {
            return true;
        }
        if ($status === 'confirmed') {
            return strtotime($startAt) - time() >= self::CANCELLATION_HOURS * 3600;
        }
        return false;
    }

    public static function deadlineMessage() {
        return 'Confirmed sessions can only be cancelled or rescheduled up to ' . self::CANCELLATION_HOURS
             . ' hours before they start. Please contact your coach.';
    }

    /** One booking of this mentee with coach, service and time details. */
    public function getMenteeBooking($bookingId, $customerId) {
        $stmt = $this->dbh->prepare(
            "SELECT b.*, s.name AS service_name, s.coach_id, s.duration_minutes, s.price, a.start_at, a.end_at,
                    cu.name AS coach_name
             FROM bookings b
             JOIN services s        ON b.service_id = s.id
             JOIN availability a    ON b.availability_id = a.id
             JOIN coach_profiles cp ON s.coach_id = cp.id
             JOIN users cu          ON cu.id = cp.user_id
             WHERE b.id = ? AND b.customer_id = ?"
        );
        $stmt->execute([$bookingId, $customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    
    public function rescheduleBooking($bookingId, $customerId, $newAvailabilityId) {
        try {
            $this->dbh->beginTransaction();

            $stmt = $this->dbh->prepare(
                "SELECT b.id, b.status, b.availability_id, s.coach_id, a.start_at
                 FROM bookings b
                 JOIN services s     ON b.service_id = s.id
                 JOIN availability a ON b.availability_id = a.id
                 WHERE b.id = ? AND b.customer_id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$bookingId, $customerId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            $error = null;
            if (!$booking) {
                $error = 'Booking not found.';
            } elseif (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
                $error = 'Only pending or confirmed bookings can be rescheduled.';
            } elseif (!self::canCustomerModify($booking['status'], $booking['start_at'])) {
                $error = self::deadlineMessage();
            } elseif ((int) $newAvailabilityId === (int) $booking['availability_id']) {
                $error = 'Please choose a different time.';
            }

            $slot = null;
            if (!$error) {
                $stmt = $this->dbh->prepare("SELECT id, coach_profile_id, start_at, is_available FROM availability WHERE id = ? FOR UPDATE");
                $stmt->execute([$newAvailabilityId]);
                $slot = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$slot || (int) $slot['coach_profile_id'] !== (int) $booking['coach_id']) {
                    $error = 'Please choose a time slot.';
                } elseif (!$slot['is_available'] || $slot['start_at'] <= date('Y-m-d H:i:s')) {
                    $error = 'Sorry, this time slot is no longer available.';
                } else {
                    $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM bookings WHERE availability_id = ? AND status IN ('pending','confirmed')");
                    $stmt->execute([$newAvailabilityId]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $error = 'Sorry, this time slot was just taken.';
                    }
                }
            }
            if ($error) {
                $this->dbh->rollBack();
                return $this->result(false, [$error]);
            }

            $this->dbh->prepare("UPDATE bookings SET availability_id = ?, status = 'pending', updated_at = NOW() WHERE id = ?")
                      ->execute([$newAvailabilityId, $bookingId]);
            $this->dbh->prepare("UPDATE availability SET is_available = 1 WHERE id = ?")->execute([$booking['availability_id']]);
            $this->dbh->prepare("UPDATE availability SET is_available = 0 WHERE id = ?")->execute([$newAvailabilityId]);

            $this->dbh->commit();
            return $this->result(true);
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return $this->result(false, ['Could not reschedule the booking. Please try again.']);
        }
    }

    /** Latest reviews of a coach for the public profile (only the first name of the reviewer). */
    public function getCoachPublicReviews($coachId, $limit = 20) {
        $stmt = $this->dbh->prepare(
            "SELECT r.rating, r.comment, r.created_at, SUBSTRING_INDEX(u.name, ' ', 1) AS first_name, s.name AS service_name
             FROM reviews r
             JOIN bookings b ON r.booking_id = b.id
             JOIN services s ON b.service_id = s.id
             JOIN users u    ON b.customer_id = u.id
             WHERE s.coach_id = ?
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute([$coachId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getAdminStats() {
        $group = function ($sql, array $defaults) {
            $out = $defaults;
            foreach ($this->dbh->query($sql)->fetchAll(PDO::FETCH_NUM) as [$key, $n]) {
                $out[$key] = (int) $n;
            }
            return $out;
        };
        $one = function ($sql) {
            return $this->dbh->query($sql)->fetchColumn();
        };

        $reviews = $this->dbh->query("SELECT COUNT(*), ROUND(AVG(rating), 1) FROM reviews")->fetch(PDO::FETCH_NUM);

        return [
            'users'    => $group("SELECT role, COUNT(*) FROM users GROUP BY role", ['admin' => 0, 'mentor' => 0, 'mentee' => 0]),
            'coaches'  => $group("SELECT status, COUNT(*) FROM coach_profiles GROUP BY status", ['pending' => 0, 'approved' => 0, 'rejected' => 0]),
            'bookings' => $group("SELECT status, COUNT(*) FROM bookings GROUP BY status", ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0]),
            'revenue'  => (float) $one("SELECT COALESCE(SUM(s.price), 0) FROM bookings b JOIN services s ON b.service_id = s.id WHERE b.status = 'completed'"),
            'services' => (int) $one("SELECT COUNT(*) FROM services WHERE is_active = 1"),
            'reviews'  => (int) $reviews[0],
            'avg_rating' => $reviews[1] !== null ? (float) $reviews[1] : null,
        ];
    }

    /** Coach applications. $status: null = all. Pending first. */
    public function getAllCoaches($status = null, $search = '') {
        $sql = "SELECT cp.id, cp.user_id, cp.status, cp.professional_title, cp.experience_years, cp.profile_image, cp.created_at,
                       u.name, u.email,
                       (SELECT COUNT(*) FROM services s WHERE s.coach_id = cp.id) AS services_count,
                       (SELECT COUNT(*) FROM bookings b JOIN services s ON b.service_id = s.id WHERE s.coach_id = cp.id) AS bookings_count,
                       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r
                          JOIN bookings b ON r.booking_id = b.id JOIN services s ON b.service_id = s.id
                          WHERE s.coach_id = cp.id) AS avg_rating
                FROM coach_profiles cp
                JOIN users u ON u.id = cp.user_id
                WHERE 1 = 1";
        $params = [];
        if ($status !== null) {
            $sql .= " AND cp.status = ?";
            $params[] = $status;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR cp.professional_title LIKE ?)";
            array_push($params, $like, $like, $like);
        }
        $stmt = $this->dbh->prepare($sql . " ORDER BY FIELD(cp.status, 'pending', 'approved', 'rejected'), cp.created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCoachProfileById($coachId) {
        $stmt = $this->dbh->prepare(
            "SELECT cp.*, u.name, u.email FROM coach_profiles cp JOIN users u ON u.id = cp.user_id WHERE cp.id = ?"
        );
        $stmt->execute([$coachId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function setCoachStatus($coachId, $status) {
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return $this->result(false, ['Invalid status.']);
        }
        $stmt = $this->dbh->prepare("UPDATE coach_profiles SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $coachId]);
        $found = $this->getCoachProfileById($coachId);
        return $this->result((bool) $found, $found ? [] : ['Coach not found.']);
    }

    public function getAllUsers($role = null, $search = '') {
        $sql = "SELECT u.id, u.name, u.email, u.role, u.created_at, cp.id AS coach_id, cp.status AS coach_status
                FROM users u LEFT JOIN coach_profiles cp ON cp.user_id = u.id WHERE 1 = 1";
        $params = [];
        if ($role !== null) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        $search = trim($search);
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            array_push($params, $like, $like);
        }
        $stmt = $this->dbh->prepare($sql . " ORDER BY u.created_at DESC, u.id DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** An admin cannot change their own role (so there is always at least one admin). */
    public function updateUserRole($userId, $role, $adminId) {
        if (!in_array($role, ['admin', 'mentee', 'mentor'], true)) {
            return $this->result(false, ['Invalid role.']);
        }
        if ((int) $userId === (int) $adminId) {
            return $this->result(false, ['You cannot change your own role.']);
        }
        $stmt = $this->dbh->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $userId]);
        $user = $this->getUserById($userId);
        return $this->result((bool) $user, $user ? [] : ['User not found.']);
    }

   
    public function deleteUser($userId, $adminId) {
        if ((int) $userId === (int) $adminId) {
            return $this->result(false, ['You cannot delete your own account.']);
        }
        if (!$this->getUserById($userId)) {
            return $this->result(false, ['User not found.']);
        }

        $count = function ($sql, $param) {
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute([$param]);
            return (int) $stmt->fetchColumn();
        };
        if ($count("SELECT COUNT(*) FROM bookings WHERE customer_id = ?", $userId) > 0) {
            return $this->result(false, ['This user has bookings, so the account cannot be deleted.']);
        }

        $stmt = $this->dbh->prepare("SELECT id, profile_image FROM coach_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($coach && $count("SELECT COUNT(*) FROM services WHERE coach_id = ?", $coach['id']) > 0) {
            return $this->result(false, ['This coach has services on record, so the account cannot be deleted. Reject the profile instead.']);
        }

        try {
            $this->dbh->beginTransaction();
            if ($coach) {
                $this->dbh->prepare("DELETE FROM availability WHERE coach_profile_id = ?")->execute([$coach['id']]);
                $this->dbh->prepare("DELETE FROM coach_profiles WHERE id = ?")->execute([$coach['id']]);
            }
            $this->dbh->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $this->dbh->commit();
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return $this->result(false, ['Could not delete the user.']);
        }
        if ($coach) {
            $this->deleteProfileImage($coach['profile_image']);
        }
        return $this->result(true);
    }

    public function getAllBookings($status = null) {
        $sql = "SELECT b.*, s.name AS service_name, s.duration_minutes, s.price, a.start_at, a.end_at,
                       cu.name AS customer_name, cu.email AS customer_email,
                       co.name AS coach_name, cp.id AS coach_id
                FROM bookings b
                JOIN services s        ON b.service_id = s.id
                JOIN availability a    ON b.availability_id = a.id
                JOIN users cu          ON b.customer_id = cu.id
                JOIN coach_profiles cp ON s.coach_id = cp.id
                JOIN users co          ON cp.user_id = co.id";
        $params = [];
        if ($status !== null) {
            $sql .= " WHERE b.status = ?";
            $params[] = $status;
        }
        $stmt = $this->dbh->prepare($sql . " ORDER BY a.start_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllServices() {
        return $this->dbh->query(
            "SELECT s.*, u.name AS coach_name, cp.status AS coach_status,
                    (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS bookings_count
             FROM services s
             JOIN coach_profiles cp ON s.coach_id = cp.id
             JOIN users u ON cp.user_id = u.id
             ORDER BY s.created_at DESC, s.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminSetServiceActive($serviceId, $active) {
        $stmt = $this->dbh->prepare("UPDATE services SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$active ? 1 : 0, $serviceId]);
        $stmt = $this->dbh->prepare("SELECT COUNT(*) FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $found = (int) $stmt->fetchColumn() > 0;
        return $this->result($found, $found ? [] : ['Service not found.']);
    }

    public function getAllReviews() {
        return $this->dbh->query(
            "SELECT r.*, cu.name AS customer_name, co.name AS coach_name, s.name AS service_name, a.start_at
             FROM reviews r
             JOIN bookings b        ON r.booking_id = b.id
             JOIN services s        ON b.service_id = s.id
             JOIN availability a    ON b.availability_id = a.id
             JOIN users cu          ON b.customer_id = cu.id
             JOIN coach_profiles cp ON s.coach_id = cp.id
             JOIN users co          ON cp.user_id = co.id
             ORDER BY r.created_at DESC, r.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteReview($reviewId) {
        $stmt = $this->dbh->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        return $this->result($stmt->rowCount() > 0, $stmt->rowCount() > 0 ? [] : ['Review not found.']);
    }
}
?>
