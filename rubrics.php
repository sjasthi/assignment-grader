<?php
declare(strict_types=1);

session_start();

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'db.php';

/*
 * Create a CSRF token for form security.
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
 * Verify that the submitted CSRF token is valid.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Redirect back to rubrics.php.
 */
function redirectToRubrics(?int $editId = null): never
{
    $location = 'rubrics.php';

    if ($editId !== null) {
        $location .= '?edit=' . $editId;
    }

    header('Location: ' . $location);
    exit;
}

/*
 * Confirm that an assignment exists.
 */
function assignmentExists(mysqli $conn, int $assignmentId): bool
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM assignments WHERE id = ?'
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $assignmentId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
}

/*
 * Get any saved flash message.
 */
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/*
 * ADD RUBRIC
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_rubric'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToRubrics();
    }

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    $criterion = trim($_POST['criterion'] ?? '');

    $points = filter_input(
        INPUT_POST,
        'points',
        FILTER_VALIDATE_INT
    );

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Please select a valid assignment.',
            'error'
        );

        redirectToRubrics();
    }

    if ($criterion === '') {
        setFlashMessage(
            'Please enter a rubric criterion.',
            'error'
        );

        redirectToRubrics();
    }

    if (strlen($criterion) > 255) {
        setFlashMessage(
            'Criterion must be 255 characters or fewer.',
            'error'
        );

        redirectToRubrics();
    }

    if ($points === false || $points === null || $points < 1) {
        setFlashMessage(
            'Points must be a positive whole number.',
            'error'
        );

        redirectToRubrics();
    }

    if ($points > 10000) {
        setFlashMessage(
            'Points cannot be greater than 10,000.',
            'error'
        );

        redirectToRubrics();
    }

    if (!assignmentExists($conn, $assignmentId)) {
        setFlashMessage(
            'The selected assignment does not exist.',
            'error'
        );

        redirectToRubrics();
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO rubrics (
            assignment_id,
            criterion,
            points
        ) VALUES (?, ?, ?)'
    );

    if (!$stmt) {
        error_log(
            'Prepare rubric insert error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the rubric insert.',
            'error'
        );

        redirectToRubrics();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isi',
        $assignmentId,
        $criterion,
        $points
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Rubric criterion added successfully.'
        );
    } else {
        error_log(
            'Add rubric error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error adding rubric criterion.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToRubrics();
}

/*
 * DELETE RUBRIC
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_rubric'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToRubrics();
    }

    $rubricId = filter_input(
        INPUT_POST,
        'rubric_id',
        FILTER_VALIDATE_INT
    );

    if (!$rubricId || $rubricId < 1) {
        setFlashMessage(
            'Invalid rubric selected.',
            'error'
        );

        redirectToRubrics();
    }

    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM rubrics WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare rubric deletion error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the rubric deletion.',
            'error'
        );

        redirectToRubrics();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $rubricId
    );

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage(
                'Rubric criterion deleted successfully.'
            );
        } else {
            setFlashMessage(
                'The selected rubric criterion was not found.',
                'error'
            );
        }
    } else {
        error_log(
            'Delete rubric error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error deleting rubric criterion.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToRubrics();
}

/*
 * UPDATE RUBRIC
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_rubric'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToRubrics();
    }

    $rubricId = filter_input(
        INPUT_POST,
        'rubric_id',
        FILTER_VALIDATE_INT
    );

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    $criterion = trim($_POST['criterion'] ?? '');

    $points = filter_input(
        INPUT_POST,
        'points',
        FILTER_VALIDATE_INT
    );

    if (!$rubricId || $rubricId < 1) {
        setFlashMessage(
            'Invalid rubric selected.',
            'error'
        );

        redirectToRubrics();
    }

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Please select a valid assignment.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    if ($criterion === '') {
        setFlashMessage(
            'Please enter a rubric criterion.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    if (strlen($criterion) > 255) {
        setFlashMessage(
            'Criterion must be 255 characters or fewer.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    if ($points === false || $points === null || $points < 1) {
        setFlashMessage(
            'Points must be a positive whole number.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    if ($points > 10000) {
        setFlashMessage(
            'Points cannot be greater than 10,000.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    if (!assignmentExists($conn, $assignmentId)) {
        setFlashMessage(
            'The selected assignment does not exist.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE rubrics
         SET assignment_id = ?,
             criterion = ?,
             points = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare rubric update error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the rubric update.',
            'error'
        );

        redirectToRubrics($rubricId);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'isii',
        $assignmentId,
        $criterion,
        $points,
        $rubricId
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Rubric criterion updated successfully.'
        );
    } else {
        error_log(
            'Update rubric error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error updating rubric criterion.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToRubrics();
}

/*
 * GET RUBRIC FOR EDITING
 */
$editRubric = null;

if (isset($_GET['edit'])) {
    $rubricId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($rubricId && $rubricId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT
                id,
                assignment_id,
                criterion,
                points
             FROM rubrics
             WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $rubricId
            );

            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);

            $editRubric =
                mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editRubric) {
            $message = 'The selected rubric criterion was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid rubric selected.';
        $messageType = 'error';
    }
}

/*
 * GET ASSIGNMENTS FOR THE DROPDOWN
 */
$assignments = mysqli_query(
    $conn,
    'SELECT
        assignments.id,
        assignments.assignment_name,
        classes.class_name
     FROM assignments
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        classes.class_name ASC,
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

/*
 * GET RUBRICS
 */
$rubrics = mysqli_query(
    $conn,
    'SELECT
        rubrics.id,
        rubrics.assignment_id,
        rubrics.criterion,
        rubrics.points,
        assignments.assignment_name,
        classes.class_name
     FROM rubrics
     LEFT JOIN assignments
        ON rubrics.assignment_id = assignments.id
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        classes.class_name ASC,
        assignments.assignment_name ASC,
        rubrics.criterion ASC'
);

if (!$rubrics) {
    error_log(
        'Load rubrics error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the rubric list.';
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

    <title>Manage Rubrics | Assignment Grader</title>

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
            <a href="assignments.php">Assignments</a>

            <a href="rubrics.php" aria-current="page">
                Rubrics
            </a>

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

        <h1>Manage Rubrics</h1>

        <p>
            Create, view, edit, and delete rubric criteria
            for each assignment.
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

            <?php if ($editRubric): ?>

                <h2>Edit Rubric Criterion</h2>

                <form method="POST" action="rubrics.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape(
                            $_SESSION['csrf_token']
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="rubric_id"
                        value="<?php echo (int) $editRubric['id']; ?>"
                    >

                    <div class="form-group">
                        <label for="edit-assignment-id">
                            Assignment
                        </label>

                        <select
                            id="edit-assignment-id"
                            name="assignment_id"
                            required
                        >
                            <option value="">
                                Select Assignment
                            </option>

                            <?php if ($assignments): ?>
                                <?php while (
                                    $assignment =
                                        mysqli_fetch_assoc($assignments)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $assignment['id']; ?>"
                                        <?php
                                        echo (
                                            (int) $assignment['id']
                                            ===
                                            (int) $editRubric['assignment_id']
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php
                                        $assignmentLabel =
                                            $assignment['assignment_name'];

                                        if (
                                            !empty($assignment['class_name'])
                                        ) {
                                            $assignmentLabel =
                                                $assignment['class_name']
                                                . ' - '
                                                . $assignmentLabel;
                                        }

                                        echo escape($assignmentLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-criterion">
                            Criterion
                        </label>

                        <input
                            type="text"
                            id="edit-criterion"
                            name="criterion"
                            value="<?php echo escape(
                                $editRubric['criterion']
                            ); ?>"
                            maxlength="255"
                            placeholder="Example: Correctness"
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
                            value="<?php echo (int) $editRubric['points']; ?>"
                            min="1"
                            max="10000"
                            step="1"
                            required
                        >
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            name="update_rubric"
                        >
                            Update Rubric
                        </button>

                        <a
                            href="rubrics.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Rubric Criterion</h2>

                <?php if (
                    $assignments
                    && mysqli_num_rows($assignments) > 0
                ): ?>

                    <form method="POST" action="rubrics.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escape(
                                $_SESSION['csrf_token']
                            ); ?>"
                        >

                        <div class="form-group">
                            <label for="assignment-id">
                                Assignment
                            </label>

                            <select
                                id="assignment-id"
                                name="assignment_id"
                                required
                            >
                                <option value="">
                                    Select Assignment
                                </option>

                                <?php while (
                                    $assignment =
                                        mysqli_fetch_assoc($assignments)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $assignment['id']; ?>"
                                    >
                                        <?php
                                        $assignmentLabel =
                                            $assignment['assignment_name'];

                                        if (
                                            !empty($assignment['class_name'])
                                        ) {
                                            $assignmentLabel =
                                                $assignment['class_name']
                                                . ' - '
                                                . $assignmentLabel;
                                        }

                                        echo escape($assignmentLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="criterion">
                                Criterion
                            </label>

                            <input
                                type="text"
                                id="criterion"
                                name="criterion"
                                placeholder="Example: Correctness"
                                maxlength="255"
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
                                placeholder="Example: 50"
                                min="1"
                                max="10000"
                                step="1"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            name="add_rubric"
                        >
                            Add Rubric
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-message">
                        You must create an assignment before adding
                        rubric criteria.

                        <a href="assignments.php">
                            Add an assignment
                        </a>
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Rubric List</h2>

            <?php if (
                $rubrics
                && mysqli_num_rows($rubrics) > 0
            ): ?>

                <div class="table-responsive">
                    <table>

                        <thead>
                            <tr>
                                <th scope="col">Class</th>
                                <th scope="col">Assignment</th>
                                <th scope="col">Criterion</th>
                                <th scope="col">Points</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while (
                                $row = mysqli_fetch_assoc($rubrics)
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
                                        <?php
                                        echo $row['assignment_name'] !== null
                                            ? escape(
                                                $row['assignment_name']
                                            )
                                            : 'Assignment not found';
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo escape(
                                            $row['criterion']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php echo (int) $row['points']; ?>
                                    </td>

                                    <td class="action-buttons">

                                        <a
                                            href="rubrics.php?edit=<?php
                                            echo (int) $row['id'];
                                            ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="rubrics.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this rubric criterion?'
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
                                                name="rubric_id"
                                                value="<?php echo (int) $row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_rubric"
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
                    No rubric criteria have been added yet.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>

</body>
</html>