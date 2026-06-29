<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if (isset($_POST['add_assignment'])) {
    $class_id = $_POST['class_id'];
    $assignment_name = $_POST['assignment_name'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $points = $_POST['points'];

    $sql = "INSERT INTO assignments (class_id, assignment_name, description, due_date, points)
            VALUES ('$class_id', '$assignment_name', '$description', '$due_date', '$points')";

    mysqli_query($conn, $sql);
}

$classes = mysqli_query($conn, "SELECT * FROM classes");

$assignments = mysqli_query($conn, "
    SELECT assignments.id,
           assignments.assignment_name,
           assignments.description,
           assignments.due_date,
           assignments.points,
           classes.class_name
    FROM assignments
    LEFT JOIN classes
    ON assignments.class_id = classes.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments</title>
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
    <h2>Manage Assignments</h2>
    <p>Create and view assignments for each class.</p>

    <div class="form-box">
        <h3>Add New Assignment</h3>

        <form method="POST">

            <label>Class</label>
            <select name="class_id" required>
                <option value="">Select Class</option>

                <?php while ($class = mysqli_fetch_assoc($classes)) { ?>
                    <option value="<?php echo $class['id']; ?>">
                        <?php echo $class['class_name']; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Assignment Name</label>
            <input type="text" name="assignment_name" placeholder="Example: Homework 1" required>

            <label>Description</label>
            <input type="text" name="description" placeholder="Example: Complete Chapter 1 questions">

            <label>Due Date</label>
            <input type="date" name="due_date" required>

            <label>Points</label>
            <input type="number" name="points" placeholder="Example: 100" required>

            <button type="submit" name="add_assignment">
                Add Assignment
            </button>

        </form>
    </div>

    <div class="table-box">
        <h3>Assignment List</h3>

        <table>
            <tr>
                <th>Class</th>
                <th>Assignment</th>
                <th>Description</th>
                <th>Due Date</th>
                <th>Points</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($assignments)) { ?>
                <tr>
                    <td><?php echo $row['class_name']; ?></td>
                    <td><?php echo $row['assignment_name']; ?></td>
                    <td><?php echo $row['description']; ?></td>
                    <td><?php echo $row['due_date']; ?></td>
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