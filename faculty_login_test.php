<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Login Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2 class="text-center mb-4">🧪 Faculty Login Test Page</h2>
                
                <div class="alert alert-info">
                    <h5>📋 Steps to Fix Login Issues:</h5>
                    <ol>
                        <li><strong>Check Faculty Database:</strong> <a href="check_faculty.php" class="btn btn-sm btn-primary">Check Faculty DB</a></li>
                        <li><strong>Reset Passwords:</strong> Use the reset button on the check page</li>
                        <li><strong>Test Login:</strong> <a href="test_faculty_login.php" class="btn btn-sm btn-success">Test Login</a></li>
                        <li><strong>Try Faculty Login:</strong> <a href="faculty_login.php" class="btn btn-sm btn-warning">Faculty Login</a></li>
                    </ol>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>🔑 Faculty Login Credentials (After Password Reset)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Faculty Name</th>
                                        <th>Email</th>
                                        <th>Password</th>
                                        <th>Subject</th>
                                        <th>Phone</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Dr. K. Subba Rao</strong></td>
                                        <td><code>subbarao@college.edu</code></td>
                                        <td><code>password123</code></td>
                                        <td>Cyber Security Essentials</td>
                                        <td>9986991545</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mr. Mukesh Gilda</strong></td>
                                        <td><code>mukesh@college.edu</code></td>
                                        <td><code>password123</code></td>
                                        <td>Cyber Crime & Digital Forensics</td>
                                        <td>9177508064</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mrs. P. Sandhya Rani</strong></td>
                                        <td><code>sandhya@college.edu</code></td>
                                        <td><code>password123</code></td>
                                        <td>Algorithms Design & Analysis</td>
                                        <td>9502060155</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mr. J. Naresh Kumar</strong></td>
                                        <td><code>naresh@college.edu</code></td>
                                        <td><code>password123</code></td>
                                        <td>DevOps</td>
                                        <td>9704768449</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Mr. R. Anbarasu</strong></td>
                                        <td><code>anbarasu@college.edu</code></td>
                                        <td><code>password123</code></td>
                                        <td>FIOT & Environmental Science</td>
                                        <td>9042932195</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5>🔧 Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="setup_database.php" class="btn btn-primary w-100">
                                    <i class="fas fa-database"></i><br>
                                    Setup Database
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="check_faculty.php" class="btn btn-info w-100">
                                    <i class="fas fa-search"></i><br>
                                    Check Faculty DB
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="test_faculty_login.php" class="btn btn-success w-100">
                                    <i class="fas fa-test"></i><br>
                                    Test Login
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="faculty_login.php" class="btn btn-warning w-100">
                                    <i class="fas fa-sign-in-alt"></i><br>
                                    Faculty Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-success mt-4">
                    <h5>✅ What I Fixed:</h5>
                    <ul>
                        <li>Fixed faculty dashboard connection error</li>
                        <li>Removed all quiz-related functionality as requested</li>
                        <li>Updated faculty login to support both hashed and plain text passwords</li>
                        <li>Created diagnostic tools to check and fix login issues</li>
                        <li>Added all new teacher data with correct contact information</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <h5>⚠️ If Login Still Fails:</h5>
                    <ol>
                        <li>Visit <code>check_faculty.php</code> and click "Reset All Passwords"</li>
                        <li>Visit <code>test_faculty_login.php</code> to verify each credential works</li>
                        <li>Try logging in with any of the emails above using password: <code>password123</code></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
