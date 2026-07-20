<?php
declare(strict_types=1);

session_start();
require_once 'db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function extractKeywords(string $criterion): array
{
    $stopWords = [
        'about','after','again','also','and','are','because','been','before',
        'being','between','both','but','can','could','does','each','for','from',
        'have','into','must','should','that','the','their','there','these','they',
        'this','those','through','use','using','was','were','what','when','where',
        'which','while','will','with','would','your'
    ];

    $clean = strtolower($criterion);
    $clean = preg_replace('/[^a-z0-9\s]/', ' ', $clean);
    $words = preg_split('/\s+/', trim((string) $clean));
    $keywords = [];

    foreach ($words as $word) {
        if (strlen($word) >= 4 && !in_array($word, $stopWords, true)) {
            $keywords[] = $word;
        }
    }

    return array_values(array_unique($keywords));
}

function generateRubricSuggestion(string $submissionText, array $rubrics): array
{
    $normalized = strtolower($submissionText);
    $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized);
    $wordCount = str_word_count($submissionText);
    $suggestedPoints = 0;
    $feedbackLines = [];

    foreach ($rubrics as $rubric) {
        $criterion = trim((string) ($rubric['criterion'] ?? ''));
        $possiblePoints = (int) ($rubric['points'] ?? 0);
        $keywords = extractKeywords($criterion);
        $matched = [];

        foreach ($keywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $normalized)) {
                $matched[] = $keyword;
            }
        }

        $keywordCount = count($keywords);
        $matchCount = count($matched);

        if ($keywordCount === 0) {
            $earned = (int) round($possiblePoints * 0.5);
            $feedbackLines[] = $criterion . ': Manual review recommended. Suggested score: '
                . $earned . '/' . $possiblePoints . '.';
        } else {
            $ratio = $matchCount / $keywordCount;

            if ($ratio >= 0.6) {
                $earned = $possiblePoints;
                $feedbackLines[] = $criterion . ': Strong evidence found. Suggested score: '
                    . $earned . '/' . $possiblePoints . '.';
            } elseif ($ratio >= 0.3) {
                $earned = (int) round($possiblePoints * 0.7);
                $feedbackLines[] = $criterion . ': Partially addressed. Add more detail. Suggested score: '
                    . $earned . '/' . $possiblePoints . '.';
            } elseif ($matchCount > 0) {
                $earned = (int) round($possiblePoints * 0.4);
                $feedbackLines[] = $criterion . ': Mentioned briefly but needs development. Suggested score: '
                    . $earned . '/' . $possiblePoints . '.';
            } else {
                $earned = 0;
                $feedbackLines[] = $criterion . ': No clear evidence detected. Suggested score: 0/'
                    . $possiblePoints . '.';
            }
        }

        $suggestedPoints += $earned;
    }

    if ($wordCount < 25) {
        $feedbackLines[] = 'Overall note: The submission is very short and should be reviewed carefully.';
    } elseif ($wordCount >= 150) {
        $feedbackLines[] = 'Overall note: The submission provides a substantial amount of written content.';
    }

    $feedbackLines[] = 'This is rule-based draft feedback. Review and edit it before saving.';

    return [
        'points' => $suggestedPoints,
        'feedback' => implode("\n\n", $feedbackLines)
    ];
}

$submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submissionId || $submissionId < 1) {
    die('Invalid submission selected.');
}

$message = '';
$messageType = 'success';

$stmt = mysqli_prepare($conn, 'SELECT
    submissions.id,
    submissions.submission_text,
    submissions.file_path,
    submissions.submitted_at,
    students.first_name,
    students.last_name,
    assignments.id AS assignment_id,
    assignments.assignment_name,
    assignments.description
FROM submissions
LEFT JOIN students ON submissions.student_id = students.student_id
LEFT JOIN assignments ON submissions.assignment_id = assignments.id
WHERE submissions.id = ?');

if (!$stmt) {
    die('Unable to prepare the submission query.');
}

mysqli_stmt_bind_param($stmt, 'i', $submissionId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$submission = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$submission) {
    die('Submission not found.');
}

$assignmentId = (int) $submission['assignment_id'];

$rubricStmt = mysqli_prepare($conn, 'SELECT criterion, points
    FROM rubrics
    WHERE assignment_id = ?
    ORDER BY id ASC');

if (!$rubricStmt) {
    die('Unable to prepare the rubric query.');
}

mysqli_stmt_bind_param($rubricStmt, 'i', $assignmentId);
mysqli_stmt_execute($rubricStmt);
$rubricResult = mysqli_stmt_get_result($rubricStmt);
$rubrics = [];
$totalPossiblePoints = 0;

while ($rubric = mysqli_fetch_assoc($rubricResult)) {
    $rubrics[] = $rubric;
    $totalPossiblePoints += (int) $rubric['points'];
}
mysqli_stmt_close($rubricStmt);

$existingGrade = null;
$existingGradeStmt = mysqli_prepare($conn, 'SELECT points_earned, feedback, graded_at
    FROM grades WHERE submission_id = ?');

if ($existingGradeStmt) {
    mysqli_stmt_bind_param($existingGradeStmt, 'i', $submissionId);
    mysqli_stmt_execute($existingGradeStmt);
    $existingGradeResult = mysqli_stmt_get_result($existingGradeStmt);
    $existingGrade = mysqli_fetch_assoc($existingGradeResult) ?: null;
    mysqli_stmt_close($existingGradeStmt);
}

$pointsValue = $existingGrade['points_earned'] ?? '';
$feedbackValue = $existingGrade['feedback'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_feedback'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Invalid form request. Please try again.';
        $messageType = 'error';
    } elseif (count($rubrics) === 0) {
        $message = 'Add at least one rubric criterion before generating feedback.';
        $messageType = 'error';
    } else {
        $submissionText = trim((string) ($submission['submission_text'] ?? ''));

        if ($submissionText === '') {
            $message = 'Automatic feedback requires submission text. Review uploaded files manually.';
            $messageType = 'error';
        } else {
            $suggestion = generateRubricSuggestion($submissionText, $rubrics);
            $pointsValue = $suggestion['points'];
            $feedbackValue = $suggestion['feedback'];
            $message = 'Draft feedback generated. Review and edit it before saving.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grade'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $message = 'Invalid form request. Please try again.';
        $messageType = 'error';
    } else {
        $pointsEarned = filter_input(INPUT_POST, 'points_earned', FILTER_VALIDATE_INT);
        $feedback = trim($_POST['feedback'] ?? '');
        $pointsValue = $_POST['points_earned'] ?? '';
        $feedbackValue = $feedback;

        if ($pointsEarned === false || $pointsEarned === null || $pointsEarned < 0) {
            $message = 'Please enter a valid score.';
            $messageType = 'error';
        } elseif ($totalPossiblePoints > 0 && $pointsEarned > $totalPossiblePoints) {
            $message = 'The score cannot be greater than ' . $totalPossiblePoints . ' points.';
            $messageType = 'error';
        } elseif ($feedback === '') {
            $message = 'Please enter feedback.';
            $messageType = 'error';
        } else {
            $gradeStmt = mysqli_prepare($conn, 'INSERT INTO grades (
                submission_id, points_earned, feedback
            ) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                points_earned = VALUES(points_earned),
                feedback = VALUES(feedback),
                graded_at = CURRENT_TIMESTAMP');

            if (!$gradeStmt) {
                $message = 'Unable to prepare the grade.';
                $messageType = 'error';
            } else {
                mysqli_stmt_bind_param($gradeStmt, 'iis', $submissionId, $pointsEarned, $feedback);

                if (mysqli_stmt_execute($gradeStmt)) {
                    $message = 'Grade saved successfully.';
                    $pointsValue = $pointsEarned;
                    $feedbackValue = $feedback;
                } else {
                    $message = 'Error saving grade.';
                    $messageType = 'error';
                }
                mysqli_stmt_close($gradeStmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission | Assignment Grader</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="cs-header">
    <div class="cs-header-container">
        <a href="index.php" class="cs-logo">Assignment Grader</a>
        <nav class="cs-nav" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="classes.php">Classes</a>
            <a href="students.php">Students</a>
            <a href="assignments.php">Assignments</a>
            <a href="rubrics.php">Rubrics</a>
            <a href="submissions.php">Submissions</a>
            <a href="grades.php">Grades</a>
        </nav>
        <a href="login.php" class="cs-header-button">Login</a>
    </div>
</header>

<main>
<section class="page-section">
    <h1>Grade Submission</h1>

    <?php if ($message !== ''): ?>
        <div class="message <?php echo escape($messageType); ?>" role="alert">
            <?php echo escape($message); ?>
        </div>
    <?php endif; ?>

    <?php $studentName = trim(($submission['first_name'] ?? '') . ' ' . ($submission['last_name'] ?? '')); ?>
    <p><strong>Student:</strong> <?php echo escape($studentName !== '' ? $studentName : 'Student not found'); ?></p>
    <p><strong>Assignment:</strong> <?php echo escape($submission['assignment_name'] ?? 'Assignment not found'); ?></p>

    <div class="form-box">
        <h2>Assignment Description</h2>
        <?php $description = trim((string) ($submission['description'] ?? '')); ?>
        <p><?php echo $description !== '' ? nl2br(escape($description)) : 'No assignment description has been added.'; ?></p>
    </div>

    <div class="form-box">
        <h2>Student Submission</h2>
        <?php $submissionText = trim((string) ($submission['submission_text'] ?? '')); ?>
        <?php if ($submissionText !== ''): ?>
            <p><?php echo nl2br(escape($submissionText)); ?></p>
        <?php else: ?>
            <p>No submission text was entered.</p>
        <?php endif; ?>
    </div>

    <div class="form-box">
        <h2>Uploaded Assignment File</h2>
        <?php if (!empty($submission['file_path'])): ?>
            <p><a href="<?php echo escape($submission['file_path']); ?>" target="_blank" rel="noopener" class="edit-button">View Uploaded File</a></p>
        <?php else: ?>
            <p>No file was uploaded for this submission.</p>
        <?php endif; ?>
    </div>

    <div class="form-box">
        <h2>Rubric</h2>
        <?php if (count($rubrics) > 0): ?>
            <?php foreach ($rubrics as $rubric): ?>
                <p><strong><?php echo escape((string) $rubric['criterion']); ?></strong> — <?php echo (int) $rubric['points']; ?> points</p>
            <?php endforeach; ?>
            <p><strong>Total possible points:</strong> <?php echo $totalPossiblePoints; ?></p>
        <?php else: ?>
            <p>No rubric has been added for this assignment.</p>
        <?php endif; ?>
    </div>

    <div class="form-box">
        <h2>Grade and Feedback</h2>
        <p>Generate Feedback creates a rule-based draft. Review it before saving.</p>

        <form method="POST" action="grade_submission.php?id=<?php echo $submissionId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo escape($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="points-earned">Points Earned</label>
                <input type="number" id="points-earned" name="points_earned" min="0"
                    <?php if ($totalPossiblePoints > 0): ?>max="<?php echo $totalPossiblePoints; ?>"<?php endif; ?>
                    value="<?php echo escape((string) $pointsValue); ?>">
                <?php if ($totalPossiblePoints > 0): ?>
                    <small>Maximum: <?php echo $totalPossiblePoints; ?> points</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="feedback">Instructor Feedback</label>
                <textarea id="feedback" name="feedback" rows="12" maxlength="10000"
                    placeholder="Generate or enter feedback..."><?php echo escape((string) $feedbackValue); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" name="generate_feedback" formnovalidate>Generate Feedback</button>
                <button type="submit" name="save_grade">Save Grade</button>
                <a href="submissions.php" class="cancel-button">Back to Submissions</a>
            </div>
        </form>
    </div>
</section>
</main>
</body>
</html>
