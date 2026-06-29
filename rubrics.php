<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if (isset($_POST['add_rubric'])) {
    $assignment_id = $_POST['assignment_id'];
    $criterion = $_POST['criterion'];
    $points = $_POST['points'];

    $sql = "INSERT INTO rubrics (assignment_id, criterion, points)
            VALUES ('$assignment_id', '$criterion', '$points')";

    mysqli_query($conn, $sql);
}

$assignments = mysqli_query($conn, "SELECT * FROM assignments");

$rubrics = mysqli_query($conn, "
    SELECT rubrics.id,
           rubrics.criterion,
           rubrics.points,
           assignments.assignment_name
    FROM rubrics
    LEFT JOIN assignments
    ON rubrics.assignment_id = assignments.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rubrics</title>
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
    <h2>Manage Rubrics</h2>
    <p>Create and view rubric criteria for each assignment.</p>

    <div class="form-box">
        <h3>Add New Rubric Criterion</h3>

        <form method="POST">

            <label>Assignment</label>
            <select name="assignment_id" required>
                <option value="">Select Assignment</option>

                <?php while ($assignment = mysqli_fetch_assoc($assignments)) { ?>
                    <option value="<?php echo $assignment['id']; ?>">
                        <?php echo $assignment['assignment_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Criterion</label>
            <input type="text" name="criterion" placeholder="Example: Correctness" required>

            <label>Points</label>
            <input type="number" name="points" placeholder="Example: 50" required>

            <button type="submit" name="add_rubric">
                Add Rubric
            </button>

        </form>
    </div>

    <div class="table-box">
        <h3>Rubric List</h3>

        <table>
            <tr>
                <th>Assignment</th>
                <th>Criterion</th>
                <th>Points</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($rubrics)) { ?>
                <tr>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['criterion']; ?></td>
                    <td><?php echo $row['points']; ?></td>
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