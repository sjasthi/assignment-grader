<?php
include 'db.php';

if (isset($_POST['add_class'])) {
    $class_name = $_POST['class_name'];
    $instructor_name = $_POST['instructor_name'];

    $sql = "INSERT INTO classes (class_name, instructor_name)
            VALUES ('$class_name', '$instructor_name')";

    mysqli_query($conn, $sql);
}

$result = mysqli_query($conn, "SELECT * FROM classes");
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header>
    <h1>Assignment Grader System</h1>
    <p>ICS 499 Capstone Project</p>

```
<nav>
    <a href="index.php">Home</a>
    <a href="classes.php">Classes</a>
    <a href="students.php">Students</a>
    <a href="assignments.php">Assignments</a>
    <a href="rubrics.php">Rubrics</a>
    <a href="submissions.php">Submissions</a>
</nav>
```

</header>

<section class="page-section">
    <h2>Manage Classes</h2>
    <p>Create and view classes for the Assignment Grader system.</p>

```
<div class="form-box">
    <h3>Add New Class</h3>

    <form method="POST">
        <label>Class Name</label>
        <input type="text" name="class_name" placeholder="Example: ICS 499" required>

        <label>Instructor</label>
        <input type="text" name="instructor_name" placeholder="Example: Professor Jasthi">

        <button type="submit" name="add_class">Add Class</button>
    </form>
</div>

<div class="table-box">
    <h3>Class List</h3>

    <table>
        <tr>
            <th>Class Name</th>
            <th>Instructor</th>
        </tr>

        <?php while($row = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $row['class_name']; ?></td>
            <td><?php echo $row['instructor_name']; ?></td>
        </tr>
        <?php } ?>
    </table>
</div>
```

</section>

<footer>
    <p>&copy; 2026 Assignment Grader System</p>
</footer>

</body>
</html>
