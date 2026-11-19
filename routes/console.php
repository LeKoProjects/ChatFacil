<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
| Cria (ou redefine a senha de) um administrador da plataforma NEXO.
| Em produção use este comando em vez do seeder, que só cria dados de demonstração.
|   php artisan nexo:admin
|   echo "senha" | php artisan nexo:admin email@dominio --name="Nome"
*/
Artisan::command('nexo:admin {email?} {--name=}', function () {
    $email = $this->argument('email') ?? text('E-mail do administrador NEXO', required: true);
    $name = $this->option('name') ?? text('Nome', default: 'Administração NEXO', required: true);
    // Sem terminal (ex.: echo "senha" | php artisan nexo:admin ...), lê a senha da entrada padrão.
    $pass = stream_isatty(STDIN)
        ? password('Senha (mínimo 12 caracteres)', required: true)
        : trim((string) fgets(STDIN));

    $validator = Validator::make(compact('email', 'name', 'pass'), [
        'email' => ['required', 'email', 'max:180'],
        'name' => ['required', 'string', 'max:120'],
        'pass' => ['required', 'string', 'min:12'],
    ], attributes: ['pass' => 'senha']);

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    $existing = User::query()->where('email', $email)->first();
    if ($existing && ! $existing->isPlatformAdmin()) {
        $this->error('Este e-mail já pertence a um usuário de empresa.');

        return 1;
    }

    User::query()->updateOrCreate(['email' => $email], [
        'name' => $name,
        'password' => $pass,
        'role' => UserRole::PlatformAdmin,
        'company_id' => null,
        'is_active' => true,
    ]);

    $this->info($existing ? "Senha de {$email} redefinida." : "Administrador {$email} criado.");

    return 0;
})->purpose('Cria ou redefine um administrador da plataforma NEXO');
