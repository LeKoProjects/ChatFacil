<?php

namespace App\Actions\Fortify;

use App\Actions\Companies\RegisterCompany;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Cadastro público = criação de uma nova empresa cliente + administrador.
 * Atendentes são criados pelo administrador da empresa (AgentController).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private readonly RegisterCompany $registerCompany) {}

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $data = Validator::make($input, [
            'company_name' => ['required', 'string', 'min:2', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registerCompany->handle($data);
    }
}
