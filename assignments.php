<?php
declare(strict_types=1);

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'db.php';

/*
 * Create a CSRF security token for forms.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * Escape database content before displaying it.
 */
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/*
 * Save a message that remains after redirecting.
 */
function setFlashMessage(string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/*
 * Validate the CSRF token.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Redirect back to assignments.php.
 */
function redirectToAssignments(?int $editId = null): never
{
    $location = 'assignments.php';

    if ($editId !== null) {
        $location .= '?edit=' . $editId;
    }

    header('Location: ' . $location);
    exit;
}

/*
 * Check whether a class exists.
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
 * Check that a date uses the YYYY-MM-DD format
 * and represents a real calendar date.
 */
function isValidDate(string $date): bool
{
    $dateObject = DateTime::createFromFormat('Y-m-d', $date);

    return $dateObject !== false
        && $dateObject->format('Y-m-d') === $date;
}

/*
 * Get flash message after redirecting.
 */
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/*
 * ADD ASSIGNMENT
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_assignment'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToAssignments();
    }

    $classId = filter_input(
        INPUT_POST,
        'class_id',
        FILTER_VALIDATE_INT
    );

    $assignmentName = trim($_POST['assignment_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');

    $points = filter_input(
        INPUT_POST,
        'points',
        FILTER_VALIDATE_INT
    );

    if (!$classId || $classId < 1) {
        setFlashMessage(
            'Please select a valid class.',
            'error'
        );

        redirectToAssignments();
    }

    if ($assignmentName === '' || $dueDate === '') {
        setFlashMessage(
            'Please fill in all required fields.',
            'error'
        );

        redirectToAssignments();
    }

    if ($points === false || $points === null || $points < 1) {
        setFlashMessage(
            'Points must be a positive whole number.',
            'error'
        );

        redirectToAssignments();
    }

    if ($points > 10000) {
        setFlashMessage(
            'Points cannot be greater than 10,000.',
            'error'
        );

        redirectToAssignments();
    }

    if (strlen($assignmentName) > 150) {
        setFlashMessage(
            'Assignment name must be 150 characters or fewer.',
            'error'
        );

        redirectToAssignments();
    }

    if (strlen($description) > 1000) {
        setFlashMessage(
            'Description must be 1,000 characters or fewer.',
            'error'
        );

        redirectToAssignments();
    }

    if (!isValidDate($dueDate)) {
        setFlashMessage(
            'Please enter a valid due date.',
            'error'
        );

        redirectToAssignments();
    }

    if (!classExists($conn, $classId)) {
        setFlashMessage(
            'The selected class does not exist.',
            'error'
        );

        redirectToAssignments();
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO assignments (
            class_id,
            assignment_name,
            description,
            due_date,
            points
        ) VALUES (?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        error_log(
            'Prepare assignment insert error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the assignment insert.',
            'error'
        );

        redirectToAssignments();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isssi',
        $classId,
        $assignmentName,
        $description,
        $dueDate,
        $points
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Assignment added successfully.'
        );
    } else {
        error_log(
            'Add assignment error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error adding assignment.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToAssignments();
}

/*
 * DELETE ASSIGNMENT
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_assignment'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToAssignments();
    }

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Invalid assignment selected.',
            'error'
        );

        redirectToAssignments();
    }

    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM assignments WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare assignment deletion error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the assignment deletion.',
            'error'
        );

        redirectToAssignments();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $assignmentId
    );

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage(
                'Assignment deleted successfully.'
            );
        } else {
            setFlashMessage(
                'The selected assignment was not found.',
                'error'
            );
        }
    } else {
        error_log(
            'Delete assignment error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error deleting assignment.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToAssignments();
}

/*
 * UPDATE ASSIGNMENT
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_assignment'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToAssignments();
    }

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    $classId = filter_input(
        INPUT_POST,
        'class_id',
        FILTER_VALIDATE_INT
    );

    $assignmentName = trim($_POST['assignment_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dueDate = trim($_POST['due_date'] ?? '');

    $points = filter_input(
        INPUT_POST,
        'points',
        FILTER_VALIDATE_INT
    );

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Invalid assignment selected.',
            'error'
        );

        redirectToAssignments();
    }

    if (!$classId || $classId < 1) {
        setFlashMessage(
            'Please select a valid class.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if ($assignmentName === '' || $dueDate === '') {
        setFlashMessage(
            'Please fill in all required fields.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if ($points === false || $points === null || $points < 1) {
        setFlashMessage(
            'Points must be a positive whole number.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if ($points > 10000) {
        setFlashMessage(
            'Points cannot be greater than 10,000.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if (strlen($assignmentName) > 150) {
        setFlashMessage(
            'Assignment name must be 150 characters or fewer.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if (strlen($description) > 1000) {
        setFlashMessage(
            'Description must be 1,000 characters or fewer.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if (!isValidDate($dueDate)) {
        setFlashMessage(
            'Please enter a valid due date.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    if (!classExists($conn, $classId)) {
        setFlashMessage(
            'The selected class does not exist.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE assignments
         SET class_id = ?,
             assignment_name = ?,
             description = ?,
             due_date = ?,
             points = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare assignment update error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the assignment update.',
            'error'
        );

        redirectToAssignments($assignmentId);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isssii',
        $classId,
        $assignmentName,
        $description,
        $dueDate,
        $points,
        $assignmentId
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Assignment updated successfully.'
        );
    } else {
        error_log(
            'Update assignment error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error updating assignment.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToAssignments();
}

/*
 * GET ASSIGNMENT FOR EDITING
 */
$editAssignment = null;

if (isset($_GET['edit'])) {
    $assignmentId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($assignmentId && $assignmentId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT
                id,
                class_id,
                assignment_name,
                description,
                due_date,
                points
             FROM assignments
             WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $assignmentId
            );

            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);

            $editAssignment =
                mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editAssignment) {
            $message = 'The selected assignment was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid assignment selected.';
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
    error_log(
        'Load classes error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the class list.';
    $messageType = 'error';
}

/*
 * GET ASSIGNMENTS
 */
$assignments = mysqli_query(
    $conn,
    'SELECT
        assignments.id,
        assignments.class_id,
        assignments.assignment_name,
        assignments.description,
        assignments.due_date,
        assignments.points,
        classes.class_name
     FROM assignments
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        assignments.due_date ASC,
        assignments.assignment_name ASC'
);

if (!$assignments) {
    error_log(
        'Load assignments error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the assignment list.';
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

    <title>Manage Assignments | Assignment Grader</title>

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
            <a href="students.php">Students</a>

            <a href="assignments.php" aria-current="page">
                Assignments
            </a>

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

        <h1>Manage Assignments</h1>

        <p>
            Create, view, edit, and delete assignments
            for each class.
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

            <?php if ($editAssignment): ?>

                <h2>Edit Assignment</h2>

                <form method="POST" action="assignments.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape(
                            $_SESSION['csrf_token']
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="assignment_id"
                        value="<?php echo (int) $editAssignment['id']; ?>"
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
                                <?php while (
                                    $class = mysqli_fetch_assoc($classes)
                                ): ?>
                                    <option
                                        value="<?php echo (int) $class['id']; ?>"
                                        <?php
                                        echo (
                                            (int) $class['id']
                                            ===
                                            (int) $editAssignment['class_id']
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php echo escape(
                                            $class['class_name']
                                        ); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-assignment-name">
                            Assignment Name
                        </label>

                        <input
                            type="text"
                            id="edit-assignment-name"
                            name="assignment_name"
                            value="<?php echo escape(
                                $editAssignment['assignment_name']
                            ); ?>"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-description">
                            Description
                        </label>

                        <textarea
                            id="edit-description"
                            name="description"
                            maxlength="1000"
                            rows="4"
                        ><?php echo escape(
                            $editAssignment['description'] ?? ''
                        ); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="edit-due-date">
                            Due Date
                        </label>

                        <input
                            type="date"
                            id="edit-due-date"
                            name="due_date"
                            value="<?php echo escape(
                                $editAssignment['due_date']
                            ); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-points">
                            Points
                        </label>

                        <input
                            type="number"
                            id="edit-points"
                            name="points"
                            value="<?php echo (int) $editAssignment['points']; ?>"
                            min="1"
                            max="10000"
                            step="1"
                            required
                        >
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            name="update_assignment"
                        >
                            Update Assignment
                        </button>

                        <a
                            href="assignments.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Assignment</h2>

                <?php if (
                    $classes
                    && mysqli_num_rows($classes) > 0
                ): ?>

                    <form method="POST" action="assignments.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escape(
                                $_SESSION['csrf_token']
                            ); ?>"
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

                                <?php while (
                                    $class = mysqli_fetch_assoc($classes)
                                ): ?>
                                    <option
                                        value="<?php echo (int) $class['id']; ?>"
                                    >
                                        <?php echo escape(
                                            $class['class_name']
                                        ); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="assignment-name">
                                Assignment Name
                            </label>

                            <input
                                type="text"
                                id="assignment-name"
                                name="assignment_name"
                                placeholder="Example: Homework 1"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="description">
                                Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                placeholder="Example: Complete Chapter 1 questions"
                                maxlength="1000"
                                rows="4"
                            ></textarea>
                        </div>

                        <div class="form-group">
                            <label for="due-date">
                                Due Date
                            </label>

                            <input
                                type="date"
                                id="due-date"
                                name="due_date"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="points">
                                Points
                            </label>

                            <input
                                type="number"
                                id="points"
                                name="points"
                                placeholder="Example: 100"
                                min="1"
                                max="10000"
                                step="1"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            name="add_assignment"
                        >
                            Add Assignment
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-message">
                        You must add a class before creating an
                        assignment.

                        <a href="classes.php">
                            Add a class
                        </a>
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Assignment List</h2>

            <?php if (
                $assignments
                && mysqli_num_rows($assignments) > 0
            ): ?>

                <div class="table-responsive">
                    <table>

                        <thead>
                            <tr>
                                <th scope="col">Class</th>
                                <th scope="col">Assignment</th>
                                <th scope="col">Description</th>
                                <th scope="col">Due Date</th>
                                <th scope="col">Points</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while (
                                $row = mysqli_fetch_assoc($assignments)
                            ): ?>

                                <tr>
                                    <td>
                                        <?php
                                        echo $row['class_name'] !== null
                                            ? escape($row['class_name'])
                                            : 'No class assigned';
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo escape(
                                            $row['assignment_name']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo $row['description'] !== ''
                                            ? nl2br(
                                                escape(
                                                    $row['description']
                                                )
                                            )
                                            : 'No description';
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo escape(
                                            date(
                                                'M j, Y',
                                                strtotime(
                                                    $row['due_date']
                                                )
                                            )
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php echo (int) $row['points']; ?>
                                    </td>

                                    <td class="action-buttons">

                                        <a
                                            href="assignments.php?edit=<?php
                                            echo (int) $row['id'];
                                            ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="assignments.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this assignment?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo escape(
                                                    $_SESSION['csrf_token']
                                                ); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="assignment_id"
                                                value="<?php echo (int) $row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_assignment"
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
                    No assignments have been added yet.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>

</body>
</html>