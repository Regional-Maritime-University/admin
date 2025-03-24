<nav class="sidebar">
    <div class="logo">
        <i class="fas fa-university"></i>
        <h2>RMU Admin</h2>
    </div>
    <div class="menu-groups">
        <div class="menu-group">
            <h3>People Management</h3>
            <div class="menu-items">
                <a href="<?= url('staffs/index.php') ?>" class="menu-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Staff</span>
                </a>
                <a href="<?= url('students/index.php') ?>" class="menu-item">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
                <a href="<?= url('applicants/index.php') ?>" class="menu-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Applicants</span>
                </a>
            </div>
        </div>
        <div class="menu-group">
            <h3>Academic</h3>
            <div class="menu-items">
                <a href="<?= url('faculties/index.php') ?>" class="menu-item">
                    <i class="fas fa-building"></i>
                    <span>Faculties</span>
                </a>
                <a href="<?= url('departments/index.php') ?>" class="menu-item">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
                <a href="<?= url('programs/index.php') ?>" class="menu-item">
                    <i class="fas fa-book"></i>
                    <span>Programs</span>
                </a>
                <a href="<?= url('courses/index.php') ?>" class="menu-item">
                    <i class="fas fa-chalkboard"></i>
                    <span>Courses</span>
                </a>
                <a href="<?= url('classes/index.php') ?>" class="menu-item">
                    <i class="fas fa-chalkboard"></i>
                    <span>Classes</span>
                </a>
            </div>
        </div>
        <div class="menu-group">
            <h3>Administration</h3>
            <div class="menu-items">
                <a href="<?= url('setups/course-registration.php') ?>" class="menu-item">
                    <i class="fas fa-pen-alt"></i>
                    <span>Course Registration</span>
                </a>
                <a href="<?= url('setups/admission-period.php') ?>" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Admission Period</span>
                </a>
                <a href="<?= url('setups/academic-year.php') ?>" class="menu-item">
                    <i class="fas fa-clock"></i>
                    <span>Academic Year</span>
                </a>
                <a href="<?= url('setups/forms.php') ?>" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Forms</span>
                </a>
                <a href="<?= url('fees/index.php') ?>" class="menu-item">
                    <i class="fas fa-file-alt"></i>
                    <span>Fees Structure</span>
                </a>
            </div>
        </div>
        <div class="menu-group">
            <h3>Reports</h3>
            <div class="menu-items">
                <a href="#" class="menu-item">
                    <i class="fas fa-pie-chart"></i>
                    <span>General Reports</span>
                </a>
            </div>
        </div>
    </div>
</nav>