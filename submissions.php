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
 * Escape content before displaying it in HTML.
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
 * Verify the CSRF token.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Redirect back to submissions.php.
 */
function redirectToSubmissions(?int $editId = null): never
{
    $location = 'submissions.php';

    if ($editId !== null) {
        $location .= '?edit=' . $editId;
    }

    header('Location: ' . $location);
    exit;
}

/*
 * Confirm that a student exists.
 */
function studentExists(mysqli $conn, int $studentId): bool
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT student_id FROM students WHERE student_id = ?'
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;

    mysqli_stmt_close($stmt);

    return $exists;
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
 * Get flash message after redirecting.
 */
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'success';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

/*
 * ADD SUBMISSION
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_submission'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToSubmissions();
    }

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    $submissionText = trim($_POST['submission_text'] ?? '');

    if (!$studentId || $studentId < 1) {
        setFlashMessage(
            'Please select a valid student.',
            'error'
        );

        redirectToSubmissions();
    }

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Please select a valid assignment.',
            'error'
        );

        redirectToSubmissions();
    }

    if ($submissionText === '') {
        setFlashMessage(
            'Please enter the submission text.',
            'error'
        );

        redirectToSubmissions();
    }

    if (strlen($submissionText) > 50000) {
        setFlashMessage(
            'Submission text must be 50,000 characters or fewer.',
            'error'
        );

        redirectToSubmissions();
    }

    if (!studentExists($conn, $studentId)) {
        setFlashMessage(
            'The selected student does not exist.',
            'error'
        );

        redirectToSubmissions();
    }

    if (!assignmentExists($conn, $assignmentId)) {
        setFlashMessage(
            'The selected assignment does not exist.',
            'error'
        );

        redirectToSubmissions();
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO submissions (
            student_id,
            assignment_id,
            submission_text
        ) VALUES (?, ?, ?)'
    );

    if (!$stmt) {
        error_log(
            'Prepare submission insert error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the submission.',
            'error'
        );

        redirectToSubmissions();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iis',
        $studentId,
        $assignmentId,
        $submissionText
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Submission added successfully.'
        );
    } else {
        error_log(
            'Add submission error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error adding submission.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToSubmissions();
}

/*
 * DELETE SUBMISSION
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_submission'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToSubmissions();
    }

    $submissionId = filter_input(
        INPUT_POST,
        'submission_id',
        FILTER_VALIDATE_INT
    );

    if (!$submissionId || $submissionId < 1) {
        setFlashMessage(
            'Invalid submission selected.',
            'error'
        );

        redirectToSubmissions();
    }

    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM submissions WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare submission deletion error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the submission deletion.',
            'error'
        );

        redirectToSubmissions();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $submissionId
    );

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage(
                'Submission deleted successfully.'
            );
        } else {
            setFlashMessage(
                'The selected submission was not found.',
                'error'
            );
        }
    } else {
        error_log(
            'Delete submission error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error deleting submission.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToSubmissions();
}

/*
 * UPDATE SUBMISSION
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_submission'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToSubmissions();
    }

    $submissionId = filter_input(
        INPUT_POST,
        'submission_id',
        FILTER_VALIDATE_INT
    );

    $studentId = filter_input(
        INPUT_POST,
        'student_id',
        FILTER_VALIDATE_INT
    );

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    $submissionText = trim($_POST['submission_text'] ?? '');

    if (!$submissionId || $submissionId < 1) {
        setFlashMessage(
            'Invalid submission selected.',
            'error'
        );

        redirectToSubmissions();
    }

    if (!$studentId || $studentId < 1) {
        setFlashMessage(
            'Please select a valid student.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    if (!$assignmentId || $assignmentId < 1) {
        setFlashMessage(
            'Please select a valid assignment.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    if ($submissionText === '') {
        setFlashMessage(
            'Please enter the submission text.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    if (strlen($submissionText) > 50000) {
        setFlashMessage(
            'Submission text must be 50,000 characters or fewer.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    if (!studentExists($conn, $studentId)) {
        setFlashMessage(
            'The selected student does not exist.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    if (!assignmentExists($conn, $assignmentId)) {
        setFlashMessage(
            'The selected assignment does not exist.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE submissions
         SET student_id = ?,
             assignment_id = ?,
             submission_text = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare submission update error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the submission update.',
            'error'
        );

        redirectToSubmissions($submissionId);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iisi',
        $studentId,
        $assignmentId,
        $submissionText,
        $submissionId
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Submission updated successfully.'
        );
    } else {
        error_log(
            'Update submission error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error updating submission.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToSubmissions();
}

/*
 * GET SUBMISSION FOR EDITING
 */
$editSubmission = null;

if (isset($_GET['edit'])) {
    $submissionId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($submissionId && $submissionId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT
                id,
                student_id,
                assignment_id,
                submission_text,
                submitted_at,
                status
             FROM submissions
             WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $submissionId
            );

            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);

            $editSubmission =
                mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editSubmission) {
            $message = 'The selected submission was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid submission selected.';
        $messageType = 'error';
    }
}

/*
 * GET STUDENTS FOR THE DROPDOWN
 */
$students = mysqli_query(
    $conn,
    'SELECT
        students.student_id,
        students.first_name,
        students.last_name,
        classes.class_name
     FROM students
     LEFT JOIN classes
        ON students.class_id = classes.id
     ORDER BY
        students.last_name ASC,
        students.first_name ASC'
);

if (!$students) {
    error_log(
        'Load students error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the student list.';
    $messageType = 'error';
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
 * GET SUBMISSIONS
 */
$submissions = mysqli_query(
    $conn,
    'SELECT
        submissions.id,
        submissions.student_id,
        submissions.assignment_id,
        submissions.submission_text,
        submissions.submitted_at,
        submissions.status,
        students.first_name,
        students.last_name,
        assignments.assignment_name,
        classes.class_name
     FROM submissions
     LEFT JOIN students
        ON submissions.student_id = students.student_id
     LEFT JOIN assignments
        ON submissions.assignment_id = assignments.id
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        submissions.submitted_at DESC,
        submissions.id DESC'
);

if (!$submissions) {
    error_log(
        'Load submissions error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the submission list.';
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

    <title>Manage Submissions | Assignment Grader</title>

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
            <a href="rubrics.php">Rubrics</a>

            <a href="submissions.php" aria-current="page">
                Submissions
            </a>

            <a href="grades.php">Grades</a>
        </nav>

        <a href="login.php" class="cs-header-button">
            Login
        </a>

    </div>
</header>

<main>
    <section class="page-section">

        <h1>Manage Submissions</h1>

        <p>
            Students can submit assignment work for review
            and feedback.
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

            <?php if ($editSubmission): ?>

                <h2>Edit Submission</h2>

                <form method="POST" action="submissions.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape(
                            $_SESSION['csrf_token']
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="submission_id"
                        value="<?php echo (int) $editSubmission['id']; ?>"
                    >

                    <div class="form-group">
                        <label for="edit-student-id">
                            Student
                        </label>

                        <select
                            id="edit-student-id"
                            name="student_id"
                            required
                        >
                            <option value="">
                                Select Student
                            </option>

                            <?php if ($students): ?>
                                <?php while (
                                    $student =
                                        mysqli_fetch_assoc($students)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $student['student_id']; ?>"
                                        <?php
                                        echo (
                                            (int) $student['student_id']
                                            ===
                                            (int) $editSubmission['student_id']
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php
                                        $studentLabel =
                                            $student['first_name']
                                            . ' '
                                            . $student['last_name'];

                                        if (!empty($student['class_name'])) {
                                            $studentLabel .=
                                                ' - '
                                                . $student['class_name'];
                                        }

                                        echo escape($studentLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

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
                                            (int) $editSubmission['assignment_id']
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
                        <label for="edit-submission-text">
                            Submission Text
                        </label>

                        <textarea
                            id="edit-submission-text"
                            name="submission_text"
                            rows="10"
                            maxlength="50000"
                            required
                        ><?php echo escape(
                            $editSubmission['submission_text']
                        ); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            name="update_submission"
                        >
                            Update Submission
                        </button>

                        <a
                            href="submissions.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Submission</h2>

                <?php if (
                    $students
                    && mysqli_num_rows($students) > 0
                    && $assignments
                    && mysqli_num_rows($assignments) > 0
                ): ?>

                    <form method="POST" action="submissions.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escape(
                                $_SESSION['csrf_token']
                            ); ?>"
                        >

                        <div class="form-group">
                            <label for="student-id">
                                Student
                            </label>

                            <select
                                id="student-id"
                                name="student_id"
                                required
                            >
                                <option value="">
                                    Select Student
                                </option>

                                <?php while (
                                    $student =
                                        mysqli_fetch_assoc($students)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $student['student_id']; ?>"
                                    >
                                        <?php
                                        $studentLabel =
                                            $student['first_name']
                                            . ' '
                                            . $student['last_name'];

                                        if (!empty($student['class_name'])) {
                                            $studentLabel .=
                                                ' - '
                                                . $student['class_name'];
                                        }

                                        echo escape($studentLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            </select>
                        </div>

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
                            <label for="submission-text">
                                Submission Text
                            </label>

                            <textarea
                                id="submission-text"
                                name="submission_text"
                                rows="10"
                                maxlength="50000"
                                placeholder="Paste the student assignment submission here..."
                                required
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            name="add_submission"
                        >
                            Submit
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-message">
                        You must have at least one student and one
                        assignment before adding a submission.
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Submission List</h2>

            <?php if (
                $submissions
                && mysqli_num_rows($submissions) > 0
            ): ?>

                <div class="table-responsive">
                    <table>

                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Class</th>
                                <th scope="col">Assignment</th>
                                <th scope="col">Submission</th>
                                <th scope="col">Submitted At</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while (
                                $row = mysqli_fetch_assoc($submissions)
                            ): ?>

                                <tr>
                                    <td>
                                        <?php
                                        if (
                                            $row['first_name'] !== null
                                            && $row['last_name'] !== null
                                        ) {
                                            echo escape(
                                                $row['first_name']
                                                . ' '
                                                . $row['last_name']
                                            );
                                        } else {
                                            echo 'Student not found';
                                        }
                                        ?>
                                    </td>

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

                                    <td class="submission-cell">
                                        <?php echo nl2br(
                                            escape(
                                                $row['submission_text']
                                            )
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php
                                        if (!empty($row['submitted_at'])) {
                                            echo escape(
                                                date(
                                                    'M j, Y g:i A',
                                                    strtotime(
                                                        $row['submitted_at']
                                                    )
                                                )
                                            );
                                        } else {
                                            echo 'Not available';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <span class="status-badge">
                                            <?php echo escape(
                                                $row['status'] ?? 'Submitted'
                                            ); ?>
                                        </span>
                                    </td>

                                    <td class="action-buttons">

                                        <a
                                            href="submissions.php?edit=<?php
                                            echo (int) $row['id'];
                                            ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="submissions.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this submission?'
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
                                                name="submission_id"
                                                value="<?php echo (int) $row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_submission"
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
                    No submissions have been added yet.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>

</body>
</html>