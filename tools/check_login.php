<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = $argv[1] ?? 'admin@admin.com';
$password = $argv[2] ?? '123123123';

$user = User::where('email', $email)->first();
if (! $user) {
    echo "User not found: $email\n";
    exit(1);
}

$ok = Hash::check($password, $user->password);
if ($ok) {
    echo "Password OK for $email\n";
    echo "User id: {$user->id}, name: {$user->name}\n";
    exit(0);
}

echo "Invalid password for $email\n";
exit(2);
