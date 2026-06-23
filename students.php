<?php
include 'db.php';

if (isset($_POST['add_student'])) {
    $class_id = $_POST['class_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];

    $sql = "INSERT INTO students (class_id, first_name, last_name, email)
            VALUES ('$class_id', '$first_name', '$last_name', '$email')";

    mysqli_query($conn, $sql);
}

$classes = mysqli_query($conn, "SELECT * FROM classes");

$students = mysqli_query($conn, "
    SELECT students.student_id,
           students.first_name,
           students.last_name,
           students.email,
           classes.class_name
    FROM students
    LEFT JOIN classes
    ON students.class_id = classes.class_id
");
?>

<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
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
    <h2>Manage Students</h2>
    <p>Add and view students in the Assignment Grader system.</p>

```
<div class="form-box">
    <h3>Add New Student</h3>

    <form method="POST">

        <label>Class</label>
        <select name="class_id" required>
            <option value="">Select Class</option>

            <?php while($class = mysqli_fetch_assoc($classes)) { ?>
                <option value="<?php echo $class['class_id']; ?>">
                    <?php echo $class['class_name']; ?>
                </option>
            <?php } ?>

        </select>

        <label>First Name</label>
        <input type="text" name="first_name" placeholder="Example: Jacob" required>

        <label>Last Name</label>
        <input type="text" name="last_name" placeholder="Example: Vang" required>

        <label>Email</label>
        <input type="email" name="email" placeholder="Example: student@email.com">

        <button type="submit" name="add_student">Add Student</button>

    </form>
</div>

<div class="table-box">
    <h3>Student List</h3>

    <table>
        <tr>
            <th>Class</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
        </tr>

        <?php while($row = mysqli_fetch_assoc($students)) { ?>
        <tr>
            <td><?php echo $row['class_name']; ?></td>
            <td><?php echo $row['first_name']; ?></td>
            <td><?php echo $row['last_name']; ?></td>
            <td><?php echo $row['email']; ?></td>
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
