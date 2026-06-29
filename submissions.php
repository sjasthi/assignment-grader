<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if (isset($_POST['add_submission'])) {
    $student_id = $_POST['student_id'];
    $assignment_id = $_POST['assignment_id'];
    $submission_text = $_POST['submission_text'];

    $sql = "INSERT INTO submissions (student_id, assignment_id, submission_text)
            VALUES ('$student_id', '$assignment_id', '$submission_text')";

    mysqli_query($conn, $sql);
}

$students = mysqli_query($conn, "SELECT * FROM students");
$assignments = mysqli_query($conn, "SELECT * FROM assignments");

$submissions = mysqli_query($conn, "
    SELECT submissions.id,
           submissions.submission_text,
           submissions.submitted_at,
           submissions.status,
           students.first_name,
           students.last_name,
           assignments.assignment_name
    FROM submissions
    LEFT JOIN students
    ON submissions.student_id = students.student_id
    LEFT JOIN assignments
    ON submissions.assignment_id = assignments.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Submissions</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="cs-header">
    <div class="cs-header-container">

        <a href="index.php" class="cs-logo">
            Assignment Grader
        </a>

        <nav class="cs-nav">
            <a href="index.php">Home</a>
            <a href="classes.php">Classes</a>
            <a href="students.php">Students</a>
            <a href="assignments.php">Assignments</a>
            <a href="rubrics.php">Rubrics</a>
            <a href="submissions.php">Submissions</a>
        </nav>

        <a href="#" class="cs-header-button">Login</a>

    </div>
</header>

<section class="page-section">
    <h2>Manage Submissions</h2>
    <p>Students can submit assignment work for review and feedback.</p>

    <div class="form-box">
        <h3>Add New Submission</h3>

        <form method="POST">

            <label>Student</label>
            <select name="student_id" required>
                <option value="">Select Student</option>

                <?php while ($student = mysqli_fetch_assoc($students)) { ?>
                    <option value="<?php echo $student['student_id']; ?>">
                        <?php echo $student['first_name'] . " " . $student['last_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Assignment</label>
            <select name="assignment_id" required>
                <option value="">Select Assignment</option>

                <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                    <option value="<?php echo $assignment['id']; ?>">
                        <?php echo $assignment['assignment_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Submission Text</label>
            <textarea name="submission_text" placeholder="Paste the student assignment submission here..." required></textarea>

            <button type="submit" name="add_submission">
                Submit
            </button>

        </form>
    </div>

    <div class="table-box">
        <h3>Submission List</h3>

        <table>
            <tr>
                <th>Student</th>
                <th>Assignment</th>
                <th>Submission</th>
                <th>Submitted At</th>
                <th>Status</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($submissions)) { ?>
                <tr>
                    <td><?php echo $row['first_name'] . " " . $row['last_name']; ?></td>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['submission_text']; ?></td>
                    <td><?php echo $row['submitted_at']; ?></td>
                    <td><?php echo $row['status']; ?></td>
                </tr>
            <?php } ?>

        </table>
    </div>
</section>

<footer id="cs-footer-1292">
    <div class="cs-container">      

        <div class="cs-logo-group">
            <a class="cs-footer-logo" href="index.php">
                Assignment Grader
            </a>

            <p class="cs-text">
                A web app for managing classes, students, assignments, rubrics, submissions, and feedback.
            </p>

            <a href="mailto:info@assignmentgrader.com" class="cs-link">
                info@assignmentgrader.com
            </a>
        </div>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">System</span></li>
            <li><a class="cs-footer-nav-link" href="classes.php">Classes</a></li>
            <li><a class="cs-footer-nav-link" href="students.php">Students</a></li>
            <li><a class="cs-footer-nav-link" href="assignments.php">Assignments</a></li>
            <li><a class="cs-footer-nav-link" href="rubrics.php">Rubrics</a></li>
        </ul>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">Project</span></li>
            <li><a class="cs-footer-nav-link" href="index.php">Home</a></li>
            <li><a class="cs-footer-nav-link" href="#">ICS 499</a></li>
            <li><a class="cs-footer-nav-link" href="#">Capstone</a></li>
            <li><a class="cs-footer-nav-link" href="#">Learn and Help</a></li>
        </ul>

        <ul class="cs-footer-nav">
            <li><span class="cs-footer-header">Team</span></li>
            <li><a class="cs-footer-nav-link" href="#">Jacob</a></li>
            <li><a class="cs-footer-nav-link" href="#">Zuhaib</a></li>
            <li><a class="cs-footer-nav-link" href="#">Suhayb</a></li>
        </ul>

    </div>

    <div class="cs-bottom">
        <span class="cs-copyright">
            Copyright © 2026.
            <a class="cs-copyright-link" href="index.php">Assignment Grader.</a>
            All Rights Reserved.
        </span>

        <a href="#" class="cs-copyright-link">Terms of Service</a>
        <a href="#" class="cs-copyright-link">Privacy Policy</a>
    </div>

    <div class="cs-floater" aria-hidden="true"></div>
</footer>

</body>
</html>