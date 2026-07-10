<?php
declare(strict_types=1);

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'db.php';

/*
 * Generate a CSRF token for protecting forms.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Store a message that will remain after redirecting.
 */
function setFlashMessage(string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/*
 * Verify that the submitted CSRF token is valid.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Escape text before displaying it in HTML.
 */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
 * Redirect back to this page after processing a form.
 * This prevents duplicate submissions when refreshing.
 */
function redirectToClasses(): never
{
    header('Location: classes.php');
    exit;
}

$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/*
 * ADD CLASS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToClasses();
    }

    $className = trim($_POST['class_name'] ?? '');
    $instructorName = trim($_POST['instructor_name'] ?? '');

    if ($className === '' || $instructorName === '') {
        setFlashMessage('Please fill in all fields.', 'error');
        redirectToClasses();
    }

    if (strlen($className) > 100 || strlen($instructorName) > 100) {
        setFlashMessage('Class and instructor names must be 100 characters or fewer.', 'error');
        redirectToClasses();
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO classes (class_name, instructor_name) VALUES (?, ?)'
    );

    if (!$stmt) {
        setFlashMessage('Unable to prepare the class insert.', 'error');
        redirectToClasses();
    }

    mysqli_stmt_bind_param($stmt, 'ss', $className, $instructorName);

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage('Class added successfully.');
    } else {
        error_log('Add class error: ' . mysqli_stmt_error($stmt));
        setFlashMessage('Error adding class.', 'error');
    }

    mysqli_stmt_close($stmt);
    redirectToClasses();
}

/*
 * DELETE CLASS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToClasses();
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        setFlashMessage('Invalid class selected.', 'error');
        redirectToClasses();
    }

    mysqli_begin_transaction($conn);

    try {
        /*
         * Delete students assigned to this class first.
         */
        $studentStmt = mysqli_prepare(
            $conn,
            'DELETE FROM students WHERE class_id = ?'
        );

        if (!$studentStmt) {
            throw new Exception('Unable to prepare student deletion.');
        }

        mysqli_stmt_bind_param($studentStmt, 'i', $id);

        if (!mysqli_stmt_execute($studentStmt)) {
            throw new Exception(mysqli_stmt_error($studentStmt));
        }

        mysqli_stmt_close($studentStmt);

        /*
         * Delete the class.
         */
        $classStmt = mysqli_prepare(
            $conn,
            'DELETE FROM classes WHERE id = ?'
        );

        if (!$classStmt) {
            throw new Exception('Unable to prepare class deletion.');
        }

        mysqli_stmt_bind_param($classStmt, 'i', $id);

        if (!mysqli_stmt_execute($classStmt)) {
            throw new Exception(mysqli_stmt_error($classStmt));
        }

        if (mysqli_stmt_affected_rows($classStmt) === 0) {
            throw new Exception('Class was not found.');
        }

        mysqli_stmt_close($classStmt);

        mysqli_commit($conn);
        setFlashMessage('Class and assigned students deleted successfully.');
    } catch (Throwable $error) {
        mysqli_rollback($conn);
        error_log('Delete class error: ' . $error->getMessage());
        setFlashMessage('Error deleting class.', 'error');
    }

    redirectToClasses();
}

/*
 * UPDATE CLASS
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class'])) {
    if (!verifyCsrfToken()) {
        setFlashMessage('Invalid form request. Please try again.', 'error');
        redirectToClasses();
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $className = trim($_POST['class_name'] ?? '');
    $instructorName = trim($_POST['instructor_name'] ?? '');

    if (!$id || $id < 1) {
        setFlashMessage('Invalid class selected.', 'error');
        redirectToClasses();
    }

    if ($className === '' || $instructorName === '') {
        setFlashMessage('Please fill in all fields.', 'error');
        header('Location: classes.php?edit=' . $id);
        exit;
    }

    if (strlen($className) > 100 || strlen($instructorName) > 100) {
        setFlashMessage(
            'Class and instructor names must be 100 characters or fewer.',
            'error'
        );
        header('Location: classes.php?edit=' . $id);
        exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE classes
         SET class_name = ?, instructor_name = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        setFlashMessage('Unable to prepare the class update.', 'error');
        redirectToClasses();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssi',
        $className,
        $instructorName,
        $id
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage('Class updated successfully.');
    } else {
        error_log('Update class error: ' . mysqli_stmt_error($stmt));
        setFlashMessage('Error updating class.', 'error');
    }

    mysqli_stmt_close($stmt);
    redirectToClasses();
}

/*
 * GET CLASS FOR EDITING
 */
$editClass = null;

if (isset($_GET['edit'])) {
    $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    if ($id && $id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, class_name, instructor_name
             FROM classes
             WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);
            $editClass = mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editClass) {
            $message = 'The selected class was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid class selected.';
        $messageType = 'error';
    }
}

/*
 * DISPLAY CLASSES
 */
$result = mysqli_query(
    $conn,
    'SELECT id, class_name, instructor_name
     FROM classes
     ORDER BY class_name ASC'
);

if (!$result) {
    error_log('Display classes error: ' . mysqli_error($conn));
    $message = 'Unable to load the class list.';
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

    <title>Manage Classes | Assignment Grader</title>

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
            <a href="classes.php" aria-current="page">Classes</a>
            <a href="students.php">Students</a>
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

        <h1>Manage Classes</h1>

        <p>
            Create, view, edit, and delete classes for the
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

            <?php if ($editClass): ?>

                <h2>Edit Class</h2>

                <form method="POST" action="classes.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape($_SESSION['csrf_token']); ?>"
                    >

                    <input
                        type="hidden"
                        name="id"
                        value="<?php echo (int) $editClass['id']; ?>"
                    >

                    <div class="form-group">
                        <label for="edit-class-name">
                            Class Name
                        </label>

                        <input
                            type="text"
                            id="edit-class-name"
                            name="class_name"
                            value="<?php echo escape($editClass['class_name']); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-instructor-name">
                            Instructor
                        </label>

                        <input
                            type="text"
                            id="edit-instructor-name"
                            name="instructor_name"
                            value="<?php echo escape($editClass['instructor_name']); ?>"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_class">
                            Update Class
                        </button>

                        <a href="classes.php" class="cancel-button">
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Class</h2>

                <form method="POST" action="classes.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape($_SESSION['csrf_token']); ?>"
                    >

                    <div class="form-group">
                        <label for="class-name">
                            Class Name
                        </label>

                        <input
                            type="text"
                            id="class-name"
                            name="class_name"
                            placeholder="Example: ICS 499"
                            maxlength="100"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="instructor-name">
                            Instructor
                        </label>

                        <input
                            type="text"
                            id="instructor-name"
                            name="instructor_name"
                            placeholder="Example: Professor Jasthi"
                            maxlength="100"
                            required
                        >
                    </div>

                    <button type="submit" name="add_class">
                        Add Class
                    </button>

                </form>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Class List</h2>

            <?php if ($result && mysqli_num_rows($result) > 0): ?>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Class Name</th>
                                <th scope="col">Instructor</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <?php echo escape($row['class_name']); ?>
                                    </td>

                                    <td>
                                        <?php echo escape($row['instructor_name']); ?>
                                    </td>

                                    <td class="action-buttons">
                                        <a
                                            href="classes.php?edit=<?php echo (int) $row['id']; ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="classes.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this class and its assigned students?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escape($_SESSION['csrf_token']); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_class"
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
                    No classes have been added yet.
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

        <span class="cs-copyright-link">Terms of Service</span>
        <span class="cs-copyright-link">Privacy Policy</span>
    </div>

    <div class="cs-floater" aria-hidden="true"></div>
</footer>

</body>
</html>