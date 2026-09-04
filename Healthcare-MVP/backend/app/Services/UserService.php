<?php 
 
require_once __DIR__ . '/../Repositories/UserRepository.php'; 
require_once __DIR__ . '/../Security/Hash.php'; 
 
class UserService 
{ 
    // Get logged-in user's profile 
    public static function getProfile(object $auth): ?array 
    { 
        return UserRepository::findById( 
            (int) $auth->sub, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Get all tenant users 
    public static function getUsers(object $auth): array 
    { 
        return UserRepository::findAllByTenant( 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Get one user 
    public static function getUser( 
        object $auth, 
        int $userId 
    ): ?array { 
        return UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Create user 
    public static function createUser( 
        object $auth, 
        string $name, 
        string $email, 
        string $password, 
        string $role 
    ): array { 
        $existingUser = UserRepository::findByEmail($email); 
 
        if ($existingUser) { 
            throw new Exception('Email already exists'); 
        } 
 
        $roleName = self::normalizeRole($role); 
 
        if ($roleName === null) { 
            throw new Exception('Invalid role'); 
        } 
 
        $passwordHash = Hash::make($password); 
 
        $userId = UserRepository::create( 
            (int) $auth->tenant_id, 
            $name, 
            $email, 
            $passwordHash 
        ); 
 
        $roleId = UserRepository::findRoleIdByName($roleName); 
 
        if ($roleId === null) { 
            throw new Exception('Role not found'); 
        } 
 
        UserRepository::assignRole($userId, $roleId); 
 
        return UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Update user 
    public static function updateUser( 
        object $auth, 
        int $userId, 
        string $name, 
        string $email 
    ): ?array { 
        $user = UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
 
        if (!$user) { 
            throw new Exception('User not found'); 
        } 
 
        $existingUser = UserRepository::findByEmail($email); 
 
        if ( 
            $existingUser && 
            (int) $existingUser['id'] !== $userId 
        ) { 
            throw new Exception('Email already exists'); 
        } 
 
        UserRepository::update( 
            $userId, 
            (int) $auth->tenant_id, 
            $name, 
            $email 
        ); 
 
        return UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Assign role 
    public static function assignRole( 
        object $auth, 
        int $userId, 
        string $role 
    ): ?array { 
        $user = UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
 
        if (!$user) { 
            throw new Exception('User not found'); 
        } 
 
        $roleName = self::normalizeRole($role); 
 
        if ($roleName === null) { 
            throw new Exception('Invalid role'); 
        } 
 
        $roleId = UserRepository::findRoleIdByName($roleName); 
 
        if ($roleId === null) { 
            throw new Exception('Role not found'); 
        } 
 
        UserRepository::removeRoles( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
 
        UserRepository::assignRole( 
            $userId, 
            $roleId 
        ); 
 
        return UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Update status 
    public static function updateStatus( 
        object $auth, 
        int $userId, 
        string $status 
    ): ?array { 
        if (!in_array($status, ['active', 'inactive'], true)) { 
            throw new Exception('Invalid status'); 
        } 
 
        $user = UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
 
        if (!$user) { 
            throw new Exception('User not found'); 
        } 
 
        UserRepository::updateStatus( 
            $userId, 
            (int) $auth->tenant_id, 
            $status 
        ); 
 
        return UserRepository::findById( 
            $userId, 
            (int) $auth->tenant_id 
        ); 
    } 
 
    // Change password 
    public static function changePassword( 
        object $auth, 
        string $currentPassword, 
        string $newPassword 
    ): bool { 
        $userId = (int) $auth->sub; 
 
        $hash = UserRepository::findPasswordHash($userId); 
 
        if (!$hash || !Hash::verify($currentPassword, $hash)) { 
            throw new Exception('Current password is incorrect'); 
        } 
 
        if (strlen($newPassword) < 8) { 
            throw new Exception( 
                'New password must be at least 8 characters' 
            ); 
        } 
 
        $newHash = Hash::make($newPassword); 
 
        return UserRepository::updatePassword( 
            $userId, 
            $newHash 
        ); 
    } 
 
    // Normalize role names 
    private static function normalizeRole(string $role): ?string 
    { 
        $roles = [ 
            'admin' => 'Admin', 
            'provider' => 'Provider', 
            'nurse' => 'Nurse', 
            'patient' => 'Patient', 
            'pharmacist' => 'Pharmacist' 
        ]; 
 
        $key = strtolower(trim($role)); 
 
        return $roles[$key] ?? null; 
    } 
}