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
 * Verify the submitted CSRF token.
 */
function verifyCsrfToken(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/*
 * Redirect back to grades.php.
 */
function redirectToGrades(?int $editId = null): never
{
    $location = 'grades.php';

    if ($editId !== null) {
        $location .= '?edit=' . $editId;
    }

    header('Location: ' . $location);
    exit;
}

/*
 * Get submission information.
 */
function getSubmission(
    mysqli $conn,
    int $submissionId
): ?array {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT
            submissions.id,
            submissions.student_id,
            submissions.assignment_id,
            assignments.points AS assignment_points
         FROM submissions
         LEFT JOIN assignments
            ON submissions.assignment_id = assignments.id
         WHERE submissions.id = ?'
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $submissionId
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $submission = mysqli_fetch_assoc($result) ?: null;

    mysqli_stmt_close($stmt);

    return $submission;
}

/*
 * Check whether another grade already exists
 * for the same submission.
 */
function gradeExistsForSubmission(
    mysqli $conn,
    int $submissionId,
    ?int $excludeGradeId = null
): bool {
    if ($excludeGradeId !== null) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id
             FROM grades
             WHERE submission_id = ?
             AND id != ?'
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $submissionId,
            $excludeGradeId
        );
    } else {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id
             FROM grades
             WHERE submission_id = ?'
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $submissionId
        );
    }

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
 * ADD GRADE
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_grade'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToGrades();
    }

    $submissionId = filter_input(
        INPUT_POST,
        'submission_id',
        FILTER_VALIDATE_INT
    );

    $pointsEarned = filter_input(
        INPUT_POST,
        'points_earned',
        FILTER_VALIDATE_INT
    );

    $feedback = trim($_POST['feedback'] ?? '');

    if (!$submissionId || $submissionId < 1) {
        setFlashMessage(
            'Please select a valid submission.',
            'error'
        );

        redirectToGrades();
    }

    /*
     * Zero is a valid grade, so do not use empty().
     */
    if (
        $pointsEarned === false
        || $pointsEarned === null
        || $pointsEarned < 0
    ) {
        setFlashMessage(
            'Points earned must be zero or a positive whole number.',
            'error'
        );

        redirectToGrades();
    }

    if ($feedback === '') {
        setFlashMessage(
            'Please enter feedback.',
            'error'
        );

        redirectToGrades();
    }

    if (strlen($feedback) > 5000) {
        setFlashMessage(
            'Feedback must be 5,000 characters or fewer.',
            'error'
        );

        redirectToGrades();
    }

    $submission = getSubmission(
        $conn,
        $submissionId
    );

    if (!$submission) {
        setFlashMessage(
            'The selected submission does not exist.',
            'error'
        );

        redirectToGrades();
    }

    /*
     * Prevent entering more points than the assignment allows.
     */
    $maximumPoints = isset($submission['assignment_points'])
        ? (int) $submission['assignment_points']
        : 0;

    if (
        $maximumPoints > 0
        && $pointsEarned > $maximumPoints
    ) {
        setFlashMessage(
            'Points earned cannot be greater than the assignment total of '
            . $maximumPoints
            . '.',
            'error'
        );

        redirectToGrades();
    }

    /*
     * Allow only one grade per submission.
     */
    if (
        gradeExistsForSubmission(
            $conn,
            $submissionId
        )
    ) {
        setFlashMessage(
            'This submission already has a grade. Edit the existing grade instead.',
            'error'
        );

        redirectToGrades();
    }

    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO grades (
            submission_id,
            points_earned,
            feedback
        ) VALUES (?, ?, ?)'
    );

    if (!$stmt) {
        error_log(
            'Prepare grade insert error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the grade.',
            'error'
        );

        redirectToGrades();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iis',
        $submissionId,
        $pointsEarned,
        $feedback
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Grade and feedback added successfully.'
        );
    } else {
        error_log(
            'Add grade error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error adding grade and feedback.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToGrades();
}

/*
 * DELETE GRADE
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_grade'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToGrades();
    }

    $gradeId = filter_input(
        INPUT_POST,
        'grade_id',
        FILTER_VALIDATE_INT
    );

    if (!$gradeId || $gradeId < 1) {
        setFlashMessage(
            'Invalid grade selected.',
            'error'
        );

        redirectToGrades();
    }

    $stmt = mysqli_prepare(
        $conn,
        'DELETE FROM grades WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare grade deletion error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the grade deletion.',
            'error'
        );

        redirectToGrades();
    }

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $gradeId
    );

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            setFlashMessage(
                'Grade deleted successfully.'
            );
        } else {
            setFlashMessage(
                'The selected grade was not found.',
                'error'
            );
        }
    } else {
        error_log(
            'Delete grade error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error deleting grade.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToGrades();
}

/*
 * UPDATE GRADE
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['update_grade'])
) {
    if (!verifyCsrfToken()) {
        setFlashMessage(
            'Invalid form request. Please try again.',
            'error'
        );

        redirectToGrades();
    }

    $gradeId = filter_input(
        INPUT_POST,
        'grade_id',
        FILTER_VALIDATE_INT
    );

    $submissionId = filter_input(
        INPUT_POST,
        'submission_id',
        FILTER_VALIDATE_INT
    );

    $pointsEarned = filter_input(
        INPUT_POST,
        'points_earned',
        FILTER_VALIDATE_INT
    );

    $feedback = trim($_POST['feedback'] ?? '');

    if (!$gradeId || $gradeId < 1) {
        setFlashMessage(
            'Invalid grade selected.',
            'error'
        );

        redirectToGrades();
    }

    if (!$submissionId || $submissionId < 1) {
        setFlashMessage(
            'Please select a valid submission.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    if (
        $pointsEarned === false
        || $pointsEarned === null
        || $pointsEarned < 0
    ) {
        setFlashMessage(
            'Points earned must be zero or a positive whole number.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    if ($feedback === '') {
        setFlashMessage(
            'Please enter feedback.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    if (strlen($feedback) > 5000) {
        setFlashMessage(
            'Feedback must be 5,000 characters or fewer.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    $submission = getSubmission(
        $conn,
        $submissionId
    );

    if (!$submission) {
        setFlashMessage(
            'The selected submission does not exist.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    $maximumPoints = isset($submission['assignment_points'])
        ? (int) $submission['assignment_points']
        : 0;

    if (
        $maximumPoints > 0
        && $pointsEarned > $maximumPoints
    ) {
        setFlashMessage(
            'Points earned cannot be greater than the assignment total of '
            . $maximumPoints
            . '.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    if (
        gradeExistsForSubmission(
            $conn,
            $submissionId,
            $gradeId
        )
    ) {
        setFlashMessage(
            'Another grade already exists for this submission.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE grades
         SET submission_id = ?,
             points_earned = ?,
             feedback = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        error_log(
            'Prepare grade update error: '
            . mysqli_error($conn)
        );

        setFlashMessage(
            'Unable to prepare the grade update.',
            'error'
        );

        redirectToGrades($gradeId);
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iisi',
        $submissionId,
        $pointsEarned,
        $feedback,
        $gradeId
    );

    if (mysqli_stmt_execute($stmt)) {
        setFlashMessage(
            'Grade and feedback updated successfully.'
        );
    } else {
        error_log(
            'Update grade error: '
            . mysqli_stmt_error($stmt)
        );

        setFlashMessage(
            'Error updating grade and feedback.',
            'error'
        );
    }

    mysqli_stmt_close($stmt);
    redirectToGrades();
}

/*
 * GET GRADE FOR EDITING
 */
$editGrade = null;

if (isset($_GET['edit'])) {
    $gradeId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($gradeId && $gradeId > 0) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT
                id,
                submission_id,
                points_earned,
                feedback,
                graded_at
             FROM grades
             WHERE id = ?'
        );

        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $gradeId
            );

            mysqli_stmt_execute($stmt);

            $editResult = mysqli_stmt_get_result($stmt);

            $editGrade =
                mysqli_fetch_assoc($editResult) ?: null;

            mysqli_stmt_close($stmt);
        }

        if (!$editGrade) {
            $message = 'The selected grade was not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid grade selected.';
        $messageType = 'error';
    }
}

/*
 * GET SUBMISSIONS FOR THE DROPDOWN
 */
$submissions = mysqli_query(
    $conn,
    'SELECT
        submissions.id,
        submissions.submission_text,
        submissions.submitted_at,
        students.first_name,
        students.last_name,
        assignments.assignment_name,
        assignments.points AS assignment_points,
        classes.class_name
     FROM submissions
     LEFT JOIN students
        ON submissions.student_id = students.student_id
     LEFT JOIN assignments
        ON submissions.assignment_id = assignments.id
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        students.last_name ASC,
        students.first_name ASC,
        assignments.assignment_name ASC'
);

if (!$submissions) {
    error_log(
        'Load submissions error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the submission list.';
    $messageType = 'error';
}

/*
 * GET GRADES
 */
$grades = mysqli_query(
    $conn,
    'SELECT
        grades.id,
        grades.submission_id,
        grades.points_earned,
        grades.feedback,
        grades.graded_at,
        submissions.submission_text,
        students.first_name,
        students.last_name,
        assignments.assignment_name,
        assignments.points AS assignment_points,
        classes.class_name
     FROM grades
     LEFT JOIN submissions
        ON grades.submission_id = submissions.id
     LEFT JOIN students
        ON submissions.student_id = students.student_id
     LEFT JOIN assignments
        ON submissions.assignment_id = assignments.id
     LEFT JOIN classes
        ON assignments.class_id = classes.id
     ORDER BY
        grades.graded_at DESC,
        grades.id DESC'
);

if (!$grades) {
    error_log(
        'Load grades error: '
        . mysqli_error($conn)
    );

    $message = 'Unable to load the grade list.';
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

    <title>Manage Grades | Assignment Grader</title>

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
            <a href="submissions.php">Submissions</a>

            <a href="grades.php" aria-current="page">
                Grades
            </a>
        </nav>

        <a href="login.php" class="cs-header-button">
            Login
        </a>

    </div>
</header>

<main>
    <section class="page-section">

        <h1>Manage Grades</h1>

        <p>
            Create, view, edit, and delete grades and feedback
            for student submissions.
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

            <?php if ($editGrade): ?>

                <h2>Edit Grade and Feedback</h2>

                <form method="POST" action="grades.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escape(
                            $_SESSION['csrf_token']
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="grade_id"
                        value="<?php echo (int) $editGrade['id']; ?>"
                    >

                    <div class="form-group">
                        <label for="edit-submission-id">
                            Submission
                        </label>

                        <select
                            id="edit-submission-id"
                            name="submission_id"
                            required
                        >
                            <option value="">
                                Select Submission
                            </option>

                            <?php if ($submissions): ?>
                                <?php while (
                                    $submission =
                                        mysqli_fetch_assoc($submissions)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $submission['id']; ?>"
                                        <?php
                                        echo (
                                            (int) $submission['id']
                                            ===
                                            (int) $editGrade['submission_id']
                                        )
                                            ? 'selected'
                                            : '';
                                        ?>
                                    >
                                        <?php
                                        $studentName =
                                            trim(
                                                ($submission['first_name'] ?? '')
                                                . ' '
                                                . ($submission['last_name'] ?? '')
                                            );

                                        if ($studentName === '') {
                                            $studentName =
                                                'Student not found';
                                        }

                                        $assignmentName =
                                            $submission['assignment_name']
                                            ?? 'Assignment not found';

                                        $submissionLabel =
                                            $studentName
                                            . ' - '
                                            . $assignmentName;

                                        if (
                                            !empty($submission['class_name'])
                                        ) {
                                            $submissionLabel =
                                                $submission['class_name']
                                                . ' - '
                                                . $submissionLabel;
                                        }

                                        $submissionLabel .=
                                            ' ('
                                            . (int) $submission['assignment_points']
                                            . ' points)';

                                        echo escape($submissionLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit-points-earned">
                            Points Earned
                        </label>

                        <input
                            type="number"
                            id="edit-points-earned"
                            name="points_earned"
                            value="<?php echo (int) $editGrade['points_earned']; ?>"
                            min="0"
                            step="1"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="edit-feedback">
                            Feedback
                        </label>

                        <textarea
                            id="edit-feedback"
                            name="feedback"
                            rows="6"
                            maxlength="5000"
                            required
                        ><?php echo escape(
                            $editGrade['feedback']
                        ); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            name="update_grade"
                        >
                            Update Grade
                        </button>

                        <a
                            href="grades.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>
                    </div>

                </form>

            <?php else: ?>

                <h2>Add New Grade and Feedback</h2>

                <?php if (
                    $submissions
                    && mysqli_num_rows($submissions) > 0
                ): ?>

                    <form method="POST" action="grades.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo escape(
                                $_SESSION['csrf_token']
                            ); ?>"
                        >

                        <div class="form-group">
                            <label for="submission-id">
                                Submission
                            </label>

                            <select
                                id="submission-id"
                                name="submission_id"
                                required
                            >
                                <option value="">
                                    Select Submission
                                </option>

                                <?php while (
                                    $submission =
                                        mysqli_fetch_assoc($submissions)
                                ): ?>

                                    <option
                                        value="<?php echo (int) $submission['id']; ?>"
                                    >
                                        <?php
                                        $studentName =
                                            trim(
                                                ($submission['first_name'] ?? '')
                                                . ' '
                                                . ($submission['last_name'] ?? '')
                                            );

                                        if ($studentName === '') {
                                            $studentName =
                                                'Student not found';
                                        }

                                        $assignmentName =
                                            $submission['assignment_name']
                                            ?? 'Assignment not found';

                                        $submissionLabel =
                                            $studentName
                                            . ' - '
                                            . $assignmentName;

                                        if (
                                            !empty($submission['class_name'])
                                        ) {
                                            $submissionLabel =
                                                $submission['class_name']
                                                . ' - '
                                                . $submissionLabel;
                                        }

                                        $submissionLabel .=
                                            ' ('
                                            . (int) $submission['assignment_points']
                                            . ' points)';

                                        echo escape($submissionLabel);
                                        ?>
                                    </option>

                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="points-earned">
                                Points Earned
                            </label>

                            <input
                                type="number"
                                id="points-earned"
                                name="points_earned"
                                placeholder="Example: 90"
                                min="0"
                                step="1"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="feedback">
                                Feedback
                            </label>

                            <textarea
                                id="feedback"
                                name="feedback"
                                rows="6"
                                maxlength="5000"
                                placeholder="Example: Good work, but add more comments."
                                required
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            name="add_grade"
                        >
                            Add Grade
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-message">
                        You must add a student submission before
                        entering a grade.

                        <a href="submissions.php">
                            Add a submission
                        </a>
                    </p>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="table-box">

            <h2>Grade List</h2>

            <?php if (
                $grades
                && mysqli_num_rows($grades) > 0
            ): ?>

                <div class="table-responsive">
                    <table>

                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">Class</th>
                                <th scope="col">Assignment</th>
                                <th scope="col">Grade</th>
                                <th scope="col">Percentage</th>
                                <th scope="col">Feedback</th>
                                <th scope="col">Graded At</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php while (
                                $row = mysqli_fetch_assoc($grades)
                            ): ?>

                                <?php
                                $maximumPoints =
                                    (int) ($row['assignment_points'] ?? 0);

                                $pointsEarned =
                                    (int) $row['points_earned'];

                                $percentage = $maximumPoints > 0
                                    ? round(
                                        ($pointsEarned / $maximumPoints)
                                        * 100,
                                        1
                                    )
                                    : null;
                                ?>

                                <tr>
                                    <td>
                                        <?php
                                        $studentName =
                                            trim(
                                                ($row['first_name'] ?? '')
                                                . ' '
                                                . ($row['last_name'] ?? '')
                                            );

                                        echo $studentName !== ''
                                            ? escape($studentName)
                                            : 'Student not found';
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

                                    <td>
                                        <strong>
                                            <?php echo $pointsEarned; ?>
                                            /
                                            <?php echo $maximumPoints; ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php if ($percentage !== null): ?>
                                            <span class="grade-badge">
                                                <?php echo $percentage; ?>%
                                            </span>
                                        <?php else: ?>
                                            Not available
                                        <?php endif; ?>
                                    </td>

                                    <td class="feedback-cell">
                                        <?php echo nl2br(
                                            escape($row['feedback'])
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php
                                        if (!empty($row['graded_at'])) {
                                            echo escape(
                                                date(
                                                    'M j, Y g:i A',
                                                    strtotime(
                                                        $row['graded_at']
                                                    )
                                                )
                                            );
                                        } else {
                                            echo 'Not available';
                                        }
                                        ?>
                                    </td>

                                    <td class="action-buttons">

                                        <a
                                            href="grades.php?edit=<?php
                                            echo (int) $row['id'];
                                            ?>"
                                            class="edit-button"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="grades.php"
                                            class="inline-form"
                                            onsubmit="return confirm(
                                                'Are you sure you want to delete this grade?'
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
                                                name="grade_id"
                                                value="<?php echo (int) $row['id']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_grade"
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
                    No grades have been added yet.
                </p>

            <?php endif; ?>

        </div>

    </section>
</main>

</body>
</html>