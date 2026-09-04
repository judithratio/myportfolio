<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/maintenance-check.php';

require_role('admin');

$pdo = db();
$pageTitle = 'Users';

/*
|--------------------------------------------------------------------------
| Portfolio URL
|--------------------------------------------------------------------------
*/
function admin_user_portfolio_url(string $email): string
{
    return BASE_URL . '/portfolio.php?email=' . rawurlencode($email);
}

function redirect_users(): never
{
    header('Location: users.php');
    exit;
}
/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        verify_csrf();

        $action = trim((string)($_POST['action'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | ADD USER
        |--------------------------------------------------------------------------
        */
        if ($action === 'add_user') {

            $email = trim((string)($_POST['email'] ?? ''));
            $status = trim((string)($_POST['account_status'] ?? 'active'));

            if ($email === '') {
                throw new RuntimeException(
                    'Google email address is required.'
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Please enter a valid email address.'
                );
            }

            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new RuntimeException(
                    'Invalid account status.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Check duplicate email
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                throw new RuntimeException(
                    'A user with this email address already exists.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Generate username
            |--------------------------------------------------------------------------
            */
            $emailPrefix = strtolower(
                (string)strtok($email, '@')
            );

            $username = preg_replace(
                '/[^a-z0-9_]/i',
                '_',
                $emailPrefix
            );

            $username = trim(
                (string)$username,
                '_'
            );

            if ($username === '') {
                $username = 'user';
            }

            $baseUsername = $username;
            $counter = 1;

            while (true) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE username = ?
                    LIMIT 1
                ");

                $stmt->execute([$username]);

                if (!$stmt->fetch()) {
                    break;
                }

                $counter++;

                $username = $baseUsername . $counter;
            }

            /*
            |--------------------------------------------------------------------------
            | Generate unusable password
            |--------------------------------------------------------------------------
            */
            $passwordHash = password_hash(
                bin2hex(random_bytes(32)),
                PASSWORD_DEFAULT
            );

            /*
            |--------------------------------------------------------------------------
            | Insert user
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username,
                    email,
                    password,
                    role,
                    account_status,
                    created_at
                )
                VALUES (?, ?, ?, 'user', ?, NOW())
            ");

            $stmt->execute([
                $username,
                $email,
                $passwordHash,
                $status
            ]);

            $userId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create profile if missing
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id
                FROM profiles
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            if (!$stmt->fetch()) {

                $stmt = $pdo->prepare("
                    INSERT INTO profiles (
                        user_id
                    )
                    VALUES (?)
                ");

                $stmt->execute([$userId]);
            }

            flash(
                'success',
                'User account added successfully.'
            );

            redirect_users();
        }


        /*
        |--------------------------------------------------------------------------
        | EDIT USER
        |--------------------------------------------------------------------------
        */
        if ($action === 'edit_user') {

            $userId = (int)($_POST['user_id'] ?? 0);
            $email = trim((string)($_POST['email'] ?? ''));
            $status = trim(
                (string)($_POST['account_status'] ?? '')
            );

            if ($userId <= 0) {
                throw new RuntimeException(
                    'Invalid user account.'
                );
            }

            if ($email === '') {
                throw new RuntimeException(
                    'Google email address is required.'
                );
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Please enter a valid email address.'
                );
            }

            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new RuntimeException(
                    'Invalid account status.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verify target user
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id, role
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            $targetUser = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$targetUser) {
                throw new RuntimeException(
                    'User account not found.'
                );
            }

            if (($targetUser['role'] ?? '') === 'admin') {
                throw new RuntimeException(
                    'Administrator accounts cannot be edited here.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Check duplicate email
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                  AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $email,
                $userId
            ]);

            if ($stmt->fetch()) {
                throw new RuntimeException(
                    'Another user is already using this email address.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update user
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    email = ?,
                    account_status = ?
                WHERE id = ?
                  AND role != 'admin'
            ");

            $stmt->execute([
                $email,
                $status,
                $userId
            ]);

            flash(
                'success',
                'User account updated successfully.'
            );

           redirect_users();
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        */
        if ($action === 'delete_user') {

            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                throw new RuntimeException(
                    'Invalid user account.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Verify target user
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id, role
                FROM users
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$userId]);

            $targetUser = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$targetUser) {
                throw new RuntimeException(
                    'User account not found.'
                );
            }

            if (($targetUser['role'] ?? '') === 'admin') {
                throw new RuntimeException(
                    'Administrator accounts cannot be deleted here.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Delete user
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE id = ?
                  AND role != 'admin'
            ");

            $stmt->execute([$userId]);

            flash(
                'success',
                'User account deleted successfully.'
            );

            redirect_users();
        }
    } catch (Throwable $e) {

        flash(
            'danger',
            $e->getMessage()
        );

        redirect_users();
    }
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/
$search = trim(
    (string)($_GET['search'] ?? '')
);


/*
|--------------------------------------------------------------------------
| SORT
|--------------------------------------------------------------------------
*/
$allowedSorts = [
    'email' => 'u.email',
    'account_status' => 'u.account_status',
    'created_at' => 'u.created_at'
];

$sort = (string)(
    $_GET['sort'] ?? 'created_at'
);

if (!isset($allowedSorts[$sort])) {
    $sort = 'created_at';
}

$order = strtoupper(
    (string)($_GET['order'] ?? 'DESC')
);

if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'DESC';
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role != 'admin'
");

$totalUsers = (int)$stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role != 'admin'
      AND account_status = 'active'
");

$activeUsers = (int)$stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role != 'admin'
      AND account_status = 'inactive'
");

$inactiveUsers = (int)$stmt->fetchColumn();


$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM users u
    INNER JOIN profiles p
        ON p.user_id = u.id
    WHERE u.role != 'admin'
      AND p.portfolio_public = 1
");

$publishedPortfolios = (int)$stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        u.id,
        u.username,
        u.email,
        u.role,
        u.account_status,
        u.created_at,
        p.full_name,
        p.profile_image,
        p.portfolio_public
    FROM users u
    LEFT JOIN profiles p
        ON p.user_id = u.id
    WHERE u.role != 'admin'
";

$params = [];


/*
|--------------------------------------------------------------------------
| Server-side Search
|--------------------------------------------------------------------------
*/
if ($search !== '') {

    $sql .= "
        AND (
            u.email LIKE ?
            OR u.username LIKE ?
            OR p.full_name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


$sql .= "
    ORDER BY {$allowedSorts[$sort]} {$order}
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| SORT URL
|--------------------------------------------------------------------------
*/
function admin_sort_url(
    string $sort,
    string $currentSort,
    string $currentOrder,
    string $search
): string {

    $nextOrder = 'ASC';

    if (
        $sort === $currentSort &&
        $currentOrder === 'ASC'
    ) {
        $nextOrder = 'DESC';
    }

    $query = http_build_query([
        'search' => $search,
        'sort' => $sort,
        'order' => $nextOrder
    ]);

    return BASE_URL .
        '/admin/users.php?' .
        $query;
}


require_once __DIR__ . '/../includes/header.php';

?>

<div class="container-fluid">

    <!-- ================================================================ -->
    <!-- PAGE HEADER -->
    <!-- ================================================================ -->

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Users
            </h1>

            <p class="mb-0 text-muted">
                Manage registered user accounts and portfolios.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary shadow-sm"
            data-toggle="modal"
            data-target="#addUserModal">

            <i class="fas fa-user-plus mr-1"></i>

            Add User

        </button>

    </div>


    <!-- ================================================================ -->
    <!-- FLASH MESSAGES -->
    <!-- ================================================================ -->

    <?php
    $success = $_SESSION['flash']['success'] ?? null;
    unset($_SESSION['flash']['success']);
    ?>

    <?php if (is_string($success) && $success !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show shadow-sm"
            role="alert">

            <i class="fas fa-check-circle mr-1"></i>

            <?= e($success) ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert"
                aria-label="Close">

                <span aria-hidden="true">
                    &times;
                </span>

            </button>

        </div>

    <?php endif; ?>


    <?php
    $danger = $_SESSION['flash']['danger'] ?? null;
    unset($_SESSION['flash']['danger']);
    ?>

    <?php if (is_string($danger) && $danger !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show shadow-sm"
            role="alert">

            <i class="fas fa-exclamation-circle mr-1"></i>

            <?= e($danger) ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert"
                aria-label="Close">

                <span aria-hidden="true">
                    &times;
                </span>

            </button>

        </div>

    <?php endif; ?>


    <!-- ================================================================ -->
    <!-- STATISTICS -->
    <!-- ================================================================ -->

    <div class="row">

        <!-- Total -->
        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Users
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalUsers) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-users fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Active -->
        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Users
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($activeUsers) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-user-check fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Inactive -->
        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-warning shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Inactive Users
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($inactiveUsers) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-user-slash fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Published -->
        <div class="col-xl-3 col-md-6 mb-4">

            <div class="card border-left-info shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Published Portfolios
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($publishedPortfolios) ?>
                            </div>

                        </div>

                        <div class="col-auto">

                            <i class="fas fa-globe fa-2x text-gray-300"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================================ -->
    <!-- USERS TABLE CARD -->
    <!-- ================================================================ -->

    <div class="card shadow mb-4 border-0">

        <!-- Card Header -->
        <div class="card-header py-3 bg-white">

            <div class="row align-items-center">

                <div class="col-md-6 mb-3 mb-md-0">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Registered Users
                    </h6>

                    <small class="text-muted">
                        View and manage user accounts.
                    </small>

                </div>


                <div class="col-md-6">

                    <!-- Server-side search -->
                    <form
                        method="GET"
                        action="<?= e(BASE_URL . '/admin/users.php') ?>">

                        <input
                            type="hidden"
                            name="sort"
                            value="<?= e($sort) ?>">

                        <input
                            type="hidden"
                            name="order"
                            value="<?= e($order) ?>">

                        <div class="input-group">

                            <input
                                type="search"
                                name="search"
                                class="form-control"
                                placeholder="Search users..."
                                value="<?= e($search) ?>">

                            <div class="input-group-append">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    title="Search">

                                    <i class="fas fa-search"></i>

                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        <!-- ============================================================ -->
        <!-- TABLE -->
        <!-- ============================================================ -->

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-bordered"
                    id="usersTable"
                    width="100%"
                    cellspacing="0">

                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Portfolio
                            </th>

                            <th>
                                Registered
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($users as $user): ?>

                            <?php

                            $userId = (int)$user['id'];

                            $fullName = trim(
                                (string)($user['full_name'] ?? '')
                            );

                            $username = trim(
                                (string)($user['username'] ?? '')
                            );

                            $email = trim(
                                (string)($user['email'] ?? '')
                            );

                            $displayName = $fullName !== ''
                                ? $fullName
                                : (
                                    $username !== ''
                                    ? $username
                                    : 'User'
                                );

                            $initial = strtoupper(
                                substr(
                                    $displayName,
                                    0,
                                    1
                                )
                            );

                            $profileImage = trim(
                                (string)(
                                    $user['profile_image'] ?? ''
                                )
                            );

                            $profileImageUrl = '';

                            if ($profileImage !== '') {

                                if (
                                    preg_match(
                                        '/^https?:\/\//i',
                                        $profileImage
                                    )
                                ) {

                                    $profileImageUrl =
                                        $profileImage;
                                } else {

                                    $profileImageUrl =
                                        asset(
                                            ltrim(
                                                $profileImage,
                                                '/'
                                            )
                                        );
                                }
                            }

                            $isActive =
                                ($user['account_status'] ?? '') === 'active';

                            $isPublished =
                                (int)(
                                    $user['portfolio_public'] ?? 0
                                ) === 1;

                            $createdAt =
                                format_date(
                                    $user['created_at']
                                );

                            ?>

                            <tr>

                                <!-- USER -->
                                <td>

                                    <div class="d-flex align-items-center">

                                        <?php if ($profileImageUrl !== ''): ?>

                                            <img
                                                src="<?= e($profileImageUrl) ?>"
                                                alt="<?= e($displayName) ?>"
                                                class="img-profile rounded-circle mr-3"
                                                style="
                                                    width: 45px;
                                                    height: 45px;
                                                    object-fit: cover;
                                                ">

                                        <?php else: ?>

                                            <div
                                                class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3"
                                                style="
                                                    width: 45px;
                                                    height: 45px;
                                                    min-width: 45px;
                                                    font-weight: 600;
                                                ">

                                                <?= e($initial) ?>

                                            </div>

                                        <?php endif; ?>


                                        <div>

                                            <div class="font-weight-bold text-gray-800">

                                                <?= e($displayName) ?>

                                            </div>


                                            <div class="small text-muted">

                                                <i class="fab fa-google mr-1"></i>

                                                <?= e($email) ?>

                                            </div>


                                            <?php if ($username !== ''): ?>

                                                <div class="small text-muted">

                                                    @<?= e($username) ?>

                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                </td>


                                <!-- STATUS -->
                                <td>

                                    <?php if ($isActive): ?>

                                        <span class="badge badge-success">

                                            <i class="fas fa-check-circle mr-1"></i>

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-secondary">

                                            <i class="fas fa-times-circle mr-1"></i>

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- PORTFOLIO -->
                                <td>

                                    <?php if ($isPublished): ?>

                                        <span class="badge badge-info">

                                            <i class="fas fa-globe mr-1"></i>

                                            Published

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-light border text-muted">

                                            <i class="fas fa-eye-slash mr-1"></i>

                                            Private

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- REGISTERED -->
                                <td>

                                    <span class="small text-gray-700">

                                        <?= e($createdAt) ?>

                                    </span>

                                </td>


                                <!-- ACTION -->
                                <td>

                                    <div class="btn-group">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-info mr-2 view-user-btn"
                                            data-toggle="modal"
                                            data-target="#viewUserModal<?= $userId ?>"
                                            title="View User">

                                            <i class="fas fa-eye"></i>

                                        </button>


                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-warning"
                                            data-toggle="modal"
                                            data-target="#editUserModal<?= $userId ?>"
                                            title="Edit User">

                                            <i class="fas fa-edit"></i>

                                        </button>

                                        
                                        <!-- <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-toggle="modal"
                                            data-target="#deleteUserModal<?= $userId ?>"
                                            title="Delete User"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button> -->
                                       

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- ====================================================================== -->
<!-- ADD USER MODAL -->
<!-- ====================================================================== -->

<div
    class="modal fade"
    id="addUserModal"
    tabindex="-1"
    role="dialog"
    aria-hidden="true">

    <div
        class="modal-dialog modal-dialog-centered"
        role="document">

        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header">

                <div>

                    <h5 class="modal-title font-weight-bold text-gray-800">

                        <i class="fas fa-user-plus text-primary mr-1"></i>

                        Add User

                    </h5>

                    <small class="text-muted">
                        Create a new user account.
                    </small>

                </div>


                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close">

                    <span aria-hidden="true">
                        &times;
                    </span>

                </button>

            </div>


            <form
                method="POST"
                action="<?= e(BASE_URL . '/admin/users.php') ?>">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="action"
                    value="add_user">


                <div class="modal-body">

                    <div class="form-group">

                        <label
                            for="addEmail"
                            class="font-weight-bold text-gray-700">
                            Google Account
                        </label>

                        <div class="input-group">

                            <div class="input-group-prepend">

                                <span class="input-group-text bg-white">

                                    <i class="fab fa-google text-danger"></i>

                                </span>

                            </div>

                            <input
                                type="email"
                                class="form-control"
                                id="addEmail"
                                name="email"
                                placeholder="user@gmail.com"
                                required>

                        </div>

                        <small class="form-text text-muted">
                            Use the user's Google email address.
                        </small>

                    </div>


                    <div class="form-group mb-0">

                        <label
                            for="addStatus"
                            class="font-weight-bold text-gray-700">
                            Account Status
                        </label>

                        <select
                            class="form-control"
                            id="addStatus"
                            name="account_status"
                            required>

                            <option value="active">
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

                    </div>

                </div>


                <div class="modal-footer bg-light">

                    <button
                        type="button"
                        class="btn btn-light border"
                        data-dismiss="modal">
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fas fa-user-plus mr-1"></i>

                        Add User

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ====================================================================== -->
<!-- USER MODALS -->
<!-- ====================================================================== -->

<?php foreach ($users as $user): ?>

    <?php

    $userId = (int)$user['id'];

    $fullName = trim(
        (string)($user['full_name'] ?? '')
    );

    $username = trim(
        (string)($user['username'] ?? '')
    );

    $email = trim(
        (string)($user['email'] ?? '')
    );

    $displayName = $fullName !== ''
        ? $fullName
        : (
            $username !== ''
            ? $username
            : 'User'
        );

    $initial = strtoupper(
        substr(
            $displayName,
            0,
            1
        )
    );

    $profileImage = trim(
        (string)(
            $user['profile_image'] ?? ''
        )
    );

    $profileImageUrl = '';

    if ($profileImage !== '') {

        if (
            preg_match(
                '/^https?:\/\//i',
                $profileImage
            )
        ) {

            $profileImageUrl =
                $profileImage;
        } else {

            $profileImageUrl =
                asset(
                    ltrim(
                        $profileImage,
                        '/'
                    )
                );
        }
    }

    $isActive =
        ($user['account_status'] ?? '') === 'active';

    $isPublished =
        (int)(
            $user['portfolio_public'] ?? 0
        ) === 1;

    ?>


    <!-- ================================================================ -->
    <!-- VIEW USER -->
    <!-- ================================================================ -->

    <div
        class="modal fade"
        id="viewUserModal<?= $userId ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
            role="document">

            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header">

                    <h5 class="modal-title font-weight-bold text-gray-800">

                        <i class="fas fa-user text-primary mr-1"></i>

                        User Details

                    </h5>


                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">

                        <span aria-hidden="true">
                            &times;
                        </span>

                    </button>

                </div>


                <div class="modal-body">

                    <!-- Profile -->
                    <div class="text-center mb-4">

                        <?php if ($profileImageUrl !== ''): ?>

                            <img
                                src="<?= e($profileImageUrl) ?>"
                                alt="<?= e($displayName) ?>"
                                class="rounded-circle shadow mb-3"
                                style="
                                    width: 90px;
                                    height: 90px;
                                    object-fit: cover;
                                ">

                        <?php else: ?>

                            <div
                                class="rounded-circle bg-primary text-white mx-auto mb-3 d-flex align-items-center justify-content-center shadow"
                                style="
                                    width: 90px;
                                    height: 90px;
                                    font-size: 30px;
                                    font-weight: 600;
                                ">

                                <?= e($initial) ?>

                            </div>

                        <?php endif; ?>


                        <h4 class="font-weight-bold text-gray-800 mb-1">

                            <?= e($displayName) ?>

                        </h4>


                        <p class="text-muted mb-2">

                            <?= e($email) ?>

                        </p>


                        <?php if ($isActive): ?>

                            <span class="badge badge-success">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge badge-secondary">
                                Inactive
                            </span>

                        <?php endif; ?>


                        <?php if ($isPublished): ?>

                            <span class="badge badge-info ml-1">
                                Portfolio Published
                            </span>

                        <?php else: ?>

                            <span class="badge badge-light border ml-1">
                                Portfolio Private
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- Account -->
                    <div class="card border mb-3">

                        <div class="card-header bg-light">

                            <h6 class="m-0 font-weight-bold text-gray-800">

                                Account Information

                            </h6>

                        </div>


                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-6 mb-3 mb-md-0">

                                    <small class="text-uppercase text-muted font-weight-bold">

                                        Google Account

                                    </small>

                                    <div class="text-gray-800 mt-1">

                                        <i class="fab fa-google text-danger mr-1"></i>

                                        <?= e($email) ?>

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <small class="text-uppercase text-muted font-weight-bold">

                                        Username

                                    </small>

                                    <div class="text-gray-800 mt-1">

                                        <?= $username !== ''
                                            ? e($username)
                                            : '—'
                                        ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Portfolio -->
                    <div class="card border">

                        <div class="card-header bg-light">

                            <h6 class="m-0 font-weight-bold text-gray-800">

                                Portfolio

                            </h6>

                        </div>


                        <div class="card-body">

                            <div class="d-flex align-items-center">

                                <div class="mr-3">

                                    <div
                                        class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                        style="
                                            width: 45px;
                                            height: 45px;
                                        ">

                                        <i class="fas fa-globe text-primary"></i>

                                    </div>

                                </div>


                                <div>

                                    <div class="font-weight-bold text-gray-800">

                                        Personal Portfolio

                                    </div>

                                    <div class="small text-muted">

                                        <?= $isPublished
                                            ? 'Publicly available'
                                            : 'Currently private'
                                        ?>

                                    </div>

                                </div>

                            </div>


                            <?php if ($isPublished): ?>

                                <div class="mt-3">

                                    <a
                                        href="<?= e(
                                                    admin_user_portfolio_url($email)
                                                ) ?>"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn btn-sm btn-primary">

                                        <i class="fas fa-external-link-alt mr-1"></i>

                                        View Portfolio

                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <div class="modal-footer bg-light">

                    <button
                        type="button"
                        class="btn btn-light border"
                        data-dismiss="modal">
                        Close
                    </button>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-dismiss="modal"
                        data-toggle="modal"
                        data-target="#editUserModal<?= $userId ?>">

                        <i class="fas fa-edit mr-1"></i>

                        Edit User

                    </button>

                </div>

            </div>

        </div>

    </div>


    <!-- ================================================================ -->
    <!-- EDIT USER -->
    <!-- ================================================================ -->

    <div
        class="modal fade"
        id="editUserModal<?= $userId ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-dialog-centered"
            role="document">

            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header">

                    <div>

                        <h5 class="modal-title font-weight-bold text-gray-800">

                            <i class="fas fa-user-edit text-primary mr-1"></i>

                            Edit User

                        </h5>

                        <small class="text-muted">

                            Update the user's account information.

                        </small>

                    </div>


                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">

                        <span aria-hidden="true">
                            &times;
                        </span>

                    </button>

                </div>


                <form
                    method="POST"
                    action="<?= e(BASE_URL . '/admin/users.php') ?>">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="edit_user">

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= $userId ?>">


                    <div class="modal-body">

                        <!-- User identity -->
                        <div class="d-flex align-items-center mb-4">

                            <?php if ($profileImageUrl !== ''): ?>

                                <img
                                    src="<?= e($profileImageUrl) ?>"
                                    alt="<?= e($displayName) ?>"
                                    class="rounded-circle shadow-sm mr-3"
                                    style="
                                        width: 55px;
                                        height: 55px;
                                        object-fit: cover;
                                    ">

                            <?php else: ?>

                                <div
                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3"
                                    style="
                                        width: 55px;
                                        height: 55px;
                                        min-width: 55px;
                                        font-size: 18px;
                                        font-weight: 600;
                                    ">

                                    <?= e($initial) ?>

                                </div>

                            <?php endif; ?>


                            <div>

                                <div class="font-weight-bold text-gray-800">

                                    <?= e($displayName) ?>

                                </div>


                                <div class="small text-muted">

                                    @<?= e($username) ?>

                                </div>

                            </div>

                        </div>


                        <div class="form-group">

                            <label
                                for="editEmail<?= $userId ?>"
                                class="font-weight-bold text-gray-700">

                                Google Account

                            </label>


                            <div class="input-group">

                                <div class="input-group-prepend">

                                    <span class="input-group-text bg-white">

                                        <i class="fab fa-google text-danger"></i>

                                    </span>

                                </div>


                                <input
                                    type="email"
                                    class="form-control"
                                    id="editEmail<?= $userId ?>"
                                    name="email"
                                    value="<?= e($email) ?>"
                                    required>

                            </div>

                        </div>


                        <div class="form-group mb-0">

                            <label
                                for="editStatus<?= $userId ?>"
                                class="font-weight-bold text-gray-700">

                                Account Status

                            </label>


                            <select
                                class="form-control"
                                id="editStatus<?= $userId ?>"
                                name="account_status"
                                required>

                                <option
                                    value="active"
                                    <?= $isActive ? 'selected' : '' ?>>
                                    Active
                                </option>


                                <option
                                    value="inactive"
                                    <?= !$isActive ? 'selected' : '' ?>>
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="modal-footer bg-light">

                        <button
                            type="button"
                            class="btn btn-light border"
                            data-dismiss="modal">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-save mr-1"></i>

                            Save Changes

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- ================================================================ -->
    <!-- DELETE USER -->
    <!-- ================================================================ -->

    <div
        class="modal fade"
        id="deleteUserModal<?= $userId ?>"
        tabindex="-1"
        role="dialog"
        aria-hidden="true">

        <div
            class="modal-dialog modal-dialog-centered"
            role="document">

            <div class="modal-content border-0 shadow-lg">

                <div class="modal-header">

                    <h5 class="modal-title font-weight-bold text-gray-800">

                        Delete User

                    </h5>


                    <button
                        type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">

                        <span aria-hidden="true">
                            &times;
                        </span>

                    </button>

                </div>


                <form
                    method="POST"
                    action="<?= e(BASE_URL . '/admin/users.php') ?>">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="delete_user">

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= $userId ?>">


                    <div class="modal-body text-center py-4">

                        <div
                            class="rounded-circle bg-danger text-white mx-auto mb-3 d-flex align-items-center justify-content-center"
                            style="
                                width: 65px;
                                height: 65px;
                            ">

                            <i class="fas fa-trash fa-lg"></i>

                        </div>


                        <h5 class="font-weight-bold text-gray-800">

                            Delete this user?

                        </h5>


                        <p class="text-muted mb-0">

                            You are about to delete

                            <strong>
                                <?= e($displayName) ?>
                            </strong>.

                        </p>

                    </div>


                    <div class="modal-footer bg-light">

                        <button
                            type="button"
                            class="btn btn-light border"
                            data-dismiss="modal">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="btn btn-danger">

                            <i class="fas fa-trash mr-1"></i>

                            Delete User

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

<?php endforeach; ?>


<!-- ====================================================================== -->
<!-- SB ADMIN 2 DATATABLES -->
<!-- ====================================================================== -->

<script>
    $(document).ready(function() {

        $('#usersTable').DataTable({

            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */
            paging: true,

            /*
            |--------------------------------------------------------------------------
            | Search
            |--------------------------------------------------------------------------
            |
            | We already have the server-side search box above the table.
            | Therefore DataTables' own search box is disabled.
            |
            |--------------------------------------------------------------------------
            */
            searching: false,

            /*
            |--------------------------------------------------------------------------
            | Ordering
            |--------------------------------------------------------------------------
            |
            | PHP handles the database sorting.
            |
            |--------------------------------------------------------------------------
            */
            ordering: false,

            /*
            |--------------------------------------------------------------------------
            | Information
            |--------------------------------------------------------------------------
            */
            info: true,

            /*
            |--------------------------------------------------------------------------
            | Length Menu
            |--------------------------------------------------------------------------
            */
            lengthChange: true,

            pageLength: 10,

            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],

            /*
            |--------------------------------------------------------------------------
            | Responsive
            |--------------------------------------------------------------------------
            */
            responsive: false,

            /*
            |--------------------------------------------------------------------------
            | Language
            |--------------------------------------------------------------------------
            */
            language: {

                lengthMenu: 'Show _MENU_ entries',

                info: 'Showing _START_ to _END_ of _TOTAL_ users',

                infoEmpty: 'Showing 0 to 0 of 0 users',

                zeroRecords: 'No users found',

                paginate: {

                    previous: '<i class="fas fa-chevron-left"></i>',

                    next: '<i class="fas fa-chevron-right"></i>'

                }

            },

            /*
            |--------------------------------------------------------------------------
            | DOM
            |--------------------------------------------------------------------------
            |
            | Uses the standard SB Admin 2 / DataTables layout.
            |
            |--------------------------------------------------------------------------
            */
            dom: '<"row"' +
                '<"col-sm-12 col-md-6"l>' +
                '>' +
                '<"row mt-2"' +
                '<"col-sm-12"tr>' +
                '>' +
                '<"row align-items-center mt-3"' +
                '<"col-sm-12 col-md-5"i>' +
                '<"col-sm-12 col-md-7"p>' +
                '>'

        });

    });
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>