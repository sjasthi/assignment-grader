<?php
declare(strict_types=1);

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'db.php';

/*
 * Create a security token for forms.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Escape content before displaying it in HTML.
 */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
 * Save a message that will remain after redirecting.
 */
function setFlashMessage(string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/*
 * Check whether the submitted form token is valid.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Redirect to students.php.
 */
function redirectToStudents(?int $editId = null): never
{
    $location = 'students.php';

    if ($editId !== null) {
        $location .= '?edit=' . $editId;
    }

    header('Location: ' . $location);
    exit;
}

/*
 * Confirm that a class exists.
 */
function classExists(mysqli $conn, int $classId): bool
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM classes WHERE id = ?'
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $classId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
}

/*
 * Get any saved message after a redirect.
 */
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/*
 * ADD STUDENT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToStudents();
    }

    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$classId || $classId < 1) {
        setFlashMessage('Please select a valid class.', 'error');
        redirectToStudents();
    }

    if ($firstName === '' || $lastName === '' || $email === '') {
        setFlashMessage('Please fill in all fields.', 'error');
        redirectToStudents();
    }

    if (
        strlen($firstName) > 50 ||
        strlen($lastName) > 50 ||
        strlen($email) > 150
    ) {
        setFlashMessage(
            'One or more fields are longer than allowed.',
            'error'
        );

        redirectToStudents();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('Please enter a valid email address.', 'error');
        redirectToStudents();
    }

    if (!classExists($conn, $classId)) {
        setFlashMessage('The selected class does not exist.', 'error');
        redirectToStudents();
    }

    /*
     * Check whether the email already exists.
     */
    $checkStmt = mysqli_prepare(
        $conn,
        'SELECT student_id FROM students WHERE email = ?'
    );

    if (!$checkStmt) {
        setFlashMessage('Unable to check the student email.', 'error');
        redirectToStudents();
    }

    mysqli_stmt_bind_param($checkStmt, 's', $email);
    mysqli_stmt_execute($checkStmt);

    $checkResult = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($checkResult) > 0) {
        mysqli_stmt_close($checkStmt);

        setFlashMessage(
            'A student with this email address already exists.',
            'error'
        );

        redirectToStudents();
    }

    mysqli_stmt_close($checkStmt);

    /*
     * Insert student using a prepared statement.
     */
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO students (
            class_id,
            first_name,
            last_name,
            email
        ) VALUES (?, ?, ?, ?)'
    );

    if (!$stmt) {
        setFlashMessage('Unable to prepare the student insert.', 'error');
        redirectToStudents();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isss',
        $classId,
        $firstName,
        $lastName,
        $email
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage('Student added successfully.');
    } else {
        error_log('Add student error: ' . mysqli_stmt_error($stmt));
        setFlashMessage('Error adding student.', 'error');
    }

    mysqli_stmt_close($stmt);
    redirectToStudents();
}

/*
 * DELETE STUDENT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToStudents();
    }

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    if (!$studentId || $studentId < 1) {
        setFlashMessage('Invalid student selected.', 'error');
        redirectToStudents();
    }

    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM students WHERE student_id = ?'
    );

    if (!$stmt) {
        setFlashMessage('Unable to prepare the student deletion.', 'error');
        redirectToStudents();
    }

    mysqli_stmt_bind_param($stmt, 'i', $studentId);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage('Student deleted successfully.');
        } else {
            setFlashMessage('The selected student was not found.', 'error');
        }
    } else {
        error_log('Delete student error: ' . mysqli_stmt_error($stmt));
        setFlashMessage('Error deleting student.', 'error');
    }

    mysqli_stmt_close($stmt);
    redirectToStudents();
}

/*
 * UPDATE STUDENT
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToStudents();
    }

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $classId = filter_input(
        INPUT_POST,
        'class_id',
        FILTER_VALIDATE_INT
    );

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$studentId || $studentId < 1) {
        setFlashMessage('Invalid student selected.', 'error');
        redirectToStudents();
    }

    if (!$classId || $classId < 1) {
        setFlashMessage('Please select a valid class.', 'error');
        redirectToStudents($studentId);
    }

    if ($firstName === '' || $lastName === '' || $email === '') {
        setFlashMessage('Please fill in all fields.', 'error');
        redirectToStudents($studentId);
    }

    if (
        strlen($firstName) > 50 ||
        strlen($lastName) > 50 ||
        strlen($email) > 150
    ) {
        setFlashMessage(
            'One or more fields are longer than allowed.',
            'error'
        );

        redirectToStudents($studentId);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('Please enter a valid email address.', 'error');
        redirectToStudents($studentId);
    }

    if (!classExists($conn, $classId)) {
        setFlashMessage('The selected class does not exist.', 'error');
        redirectToStudents($studentId);
    }

    /*
     * Make sure another student is not using the same email.
     */
    $emailStmt = mysqli_prepare(
        $conn,
        'SELECT student_id
         FROM students
         WHERE email = ?
         AND student_id != ?'
    );

    if (!$emailStmt) {
        setFlashMessage('Unable to check the student email.', 'error');
        redirectToStudents($studentId);
    }

    mysqli_stmt_bind_param(
        $emailStmt,
        'si',
        $email,
        $studentId
    );

    mysqli_stmt_execute($emailStmt);

    $emailResult = mysqli_stmt_get_result($emailStmt);

    if (mysqli_num_rows($emailResult) > 0) {
        mysqli_stmt_close($emailStmt);

        setFlashMessage(
            'Another student already uses this email address.',
            'error'
        );

        redirectToStudents($studentId);
    }

    mysqli_stmt_close($emailStmt);

    /*
     * Update the student.
     */
    $stmt = mysqli_prepare(
        $conn,
        'UPDATE students
         SET class_id = ?,
             first_name = ?,
             last_name = ?,
             email = ?
         WHERE student_id = ?'
    );

    if (!$stmt) {
        setFlashMessage('Unable to prepare the student update.', 'error');
        redirectToStudents($studentId);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isssi',
        $classId,
        $firstName,
        $lastName,
        $email,
        $studentId
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage('Student updated successfully.');
    } else {
        error_log('Update student error: ' . mysqli_stmt_error($stmt));
        setFlashMessage('Error updating student.', 'error');
    }

    mysqli_stmt_close($stmt);
    redirectToStudents();
}

/*
 * GET STUDENT FOR EDITING
 */
$editStudent = null;

if (isset($_GET['edit'])) {
    $studentId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($studentId && $studentId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT
                student_id,
                class_id,
                first_name,
                last_name,
                email
             FROM students
             WHERE student_id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $studentId);
            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);
            $editStudent = mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editStudent) {
            $message = 'The selected student was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid student selected.';
        $messageType = 'error';
    }
}

/*
 * GET CLASSES FOR THE DROPDOWN
 */
$classes = mysqli_query(
    $conn,
    'SELECT id, class_name
     FROM classes
     ORDER BY class_name ASC'
);

if (!$classes) {
    error_log('Load classes error: ' . mysqli_error($conn));

    $message = 'Unable to load the class list.';
    $messageType = 'error';
}

/*
 * GET STUDENTS
 */
$students = mysqli_query(
    $conn,
    'SELECT
        students.student_id,
        students.class_id,
        students.first_name,
        students.last_name,
        students.email,
        classes.class_name
     FROM students
     LEFT JOIN classes
        ON students.class_id = classes.id
     ORDER BY
        students.last_name ASC,
        students.first_name ASC'
);

if (!$students) {
    error_log('Load students error: ' . mysqli_error($conn));

    $message = 'Unable to load the student list.';
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Students | Assignment Grader</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="cs-header">
    <div class="cs-header-container">

        <a href="index.php" class="cs-logo">
            Assignment Grader
        </a>

        <nav class="cs-nav" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="classes.php">Classes</a>
            <a href="students.php" aria-current="page">Students</a>
            <a href="assignments.php">Assignments</a>
            <a href="rubrics.php">Rubrics</a>
            <a href="submissions.php">Submissions</a>
            <a href="grades.php">Grades</a>
        </nav>

        <a href="login.php" class="cs-header-button">
            Login
        </a>

    </div>
</header>

<main>
    <section class="page-section">

        <h1>Manage Students</h1>

        <p>
            Add, view, edit, and delete students in the
            Assignment Grader System.
        </p>

        <?php if ($message !== ''): ?>
            <div
                class="message <?php echo escape($messageType); ?>"
                role="alert"
            >
                <?php echo escape($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-box">

            <?php if ($editStudent): ?>

                <h2>Edit Student</h2>

                <form method="POST" action="students.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape($_SESSION['csrf_token']); ?>"
                    >

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?php echo (int) $editStudent['student_id']; ?>"
                    >

                    <div class="form-group">
                        <label for="edit-class-id">
                            Class
                        </label>

                        <select
                            id="edit-class-id"
                            name="class_id"
                            required
                        >
                            <option value="">
                                Select Class
                            </option>

                            <?php if ($classes): ?>
                                <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                                    <option
                                        value="<?php echo (int) $class['id']; ?>"
                                        <?php
                                        echo (
                                            (int) $class['id'] ===
                                            (int) $editStudent['class_id']
                                        ) ? 'selected' : '';
                                        ?>
                                    >
                                        <?php echo escape($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-first-name">
                            First Name
                        </label>

                        <input
                            type="text"
                            id="edit-first-name"
                            name="first_name"
                            value="<?php echo escape($editStudent['first_name']); ?>"
                            maxlength="50"
                            autocomplete="given-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-last-name">
                            Last Name
                        </label>

                        <input
                            type="text"
                            id="edit-last-name"
                            name="last_name"
                            value="<?php echo escape($editStudent['last_name']); ?>"
                            maxlength="50"
                            autocomplete="family-name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="edit-email"
                            name="email"
                            value="<?php echo escape($editStudent['email']); ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_student">
                            Update Student
                        </button>

                        <a href="students.php" class="cancel-button">
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Student</h2>

                <?php if ($classes && mysqli_num_rows($classes) > 0): ?>

                    <form method="POST" action="students.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escape($_SESSION['csrf_token']); ?>"
                        >

                        <div class="form-group">
                            <label for="class-id">
                                Class
                            </label>

                            <select
                                id="class-id"
                                name="class_id"
                                required
                            >
                                <option value="">
                                    Select Class
                                </option>

                                <?php while ($class = mysqli_fetch_assoc($classes)): ?>
                                    <option value="<?php echo (int) $class['id']; ?>">
                                        <?php echo escape($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="first-name">
                                First Name
                            </label>

                            <input
                                type="text"
                                id="first-name"
                                name="first_name"
                                placeholder="Example: Jacob"
                                maxlength="50"
                                autocomplete="given-name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="last-name">
                                Last Name
                            </label>

                            <input
                                type="text"
                                id="last-name"
                                name="last_name"
                                placeholder="Example: Vang"
                                maxlength="50"
                                autocomplete="family-name"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Example: student@email.com"
                                maxlength="150"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <button type="submit" name="add_student">
                            Add Student
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-message">
                        You must add a class before adding a student.
                        <a href="classes.php">Add a class</a>
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Student List</h2>

            <?php if ($students && mysqli_num_rows($students) > 0): ?>

                <div class="table-responsive">
                    <table>

                        <thead>
                            <tr>
                                <th scope="col">Class</th>
                                <th scope="col">First Name</th>
                                <th scope="col">Last Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($students)): ?>

                                <tr>
                                    <td>
                                        <?php
                                        echo $row['class_name'] !== null
                                            ? escape($row['class_name'])
                                            : 'No class assigned';
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo escape($row['first_name']); ?>
                                    </td>

                                    <td>
                                        <?php echo escape($row['last_name']); ?>
                                    </td>

                                    <td>
                                        <a
                                            href="mailto:<?php echo escape($row['email']); ?>"
                                        >
                                            <?php echo escape($row['email']); ?>
                                        </a>
                                    </td>

                                    <td class="action-buttons">

                                        <a
                                            href="students.php?edit=<?php echo (int) $row['student_id']; ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="students.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this student?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escape($_SESSION['csrf_token']); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value="<?php echo (int) $row['student_id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_student"
                                                class="delete-button"
                                            >
                                                Delete
                                            </button>
                                        </form>

                                    </td>
                                </tr>

                            <?php endwhile; ?>
                        </tbody>

                    </table>
                </div>

            <?php else: ?>

                <p class="empty-message">
                    No students have been added yet.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>

<footer id="cs-footer-1292">
    <div class="cs-container">

        <div class="cs-logo-group">
            <a class="cs-footer-logo" href="index.php">
                Assignment Grader
            </a>

            <p class="cs-text">
                A web app for managing classes, students, assignments,
                rubrics, submissions, and feedback.
            </p>

            <a
                href="mailto:info@assignmentgrader.com"
                class="cs-link"
            >
                info@assignmentgrader.com
            </a>
        </div>

        <ul class="cs-footer-nav">
            <li>
                <span class="cs-footer-header">System</span>
            </li>

            <li>
                <a class="cs-footer-nav-link" href="classes.php">
                    Classes
                </a>
            </li>

            <li>
                <a class="cs-footer-nav-link" href="students.php">
                    Students
                </a>
            </li>

            <li>
                <a class="cs-footer-nav-link" href="assignments.php">
                    Assignments
                </a>
            </li>

            <li>
                <a class="cs-footer-nav-link" href="rubrics.php">
                    Rubrics
                </a>
            </li>
        </ul>

        <ul class="cs-footer-nav">
            <li>
                <span class="cs-footer-header">Project</span>
            </li>

            <li>
                <a class="cs-footer-nav-link" href="index.php">
                    Home
                </a>
            </li>

            <li><span class="cs-footer-nav-link">ICS 499</span></li>
            <li><span class="cs-footer-nav-link">Capstone</span></li>
            <li><span class="cs-footer-nav-link">Learn and Help</span></li>
        </ul>

        <ul class="cs-footer-nav">
            <li>
                <span class="cs-footer-header">Team</span>
            </li>

            <li><span class="cs-footer-nav-link">Jacob</span></li>
            <li><span class="cs-footer-nav-link">Zuhaib</span></li>
            <li><span class="cs-footer-nav-link">Suhayb</span></li>
        </ul>

    </div>

    <div class="cs-bottom">
        <span class="cs-copyright">
            Copyright © 2026.

            <a class="cs-copyright-link" href="index.php">
                Assignment Grader.
            </a>

            All Rights Reserved.
        </span>

        <span class="cs-copyright-link">
            Terms of Service
        </span>

        <span class="cs-copyright-link">
            Privacy Policy
        </span>
    </div>

    <div class="cs-floater" aria-hidden="true"></div>
</footer>

</body>
</html>