<?php

/*
| Mensagens de validação em português (regras usadas pelo NEXO Chat).
| Regras não listadas aqui caem no idioma de fallback (en).
*/
return [
    'array' => 'O campo :attribute deve ser uma lista.',
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha está incorreta.',
    'email' => 'Informe um e-mail válido.',
    'enum' => 'O valor de :attribute é inválido.',
    'in' => 'O valor de :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'array' => 'O campo :attribute deve ter no máximo :max itens.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
    ],
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'A senha deve conter pelo menos uma letra.',
        'mixed' => 'A senha deve conter letras maiúsculas e minúsculas.',
        'numbers' => 'A senha deve conter pelo menos um número.',
        'symbols' => 'A senha deve conter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
    ],
    'regex' => 'O formato de :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'size' => [
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'uuid' => 'O campo :attribute deve ser um UUID válido.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'phone' => 'telefone',
        'message' => 'mensagem',
        'body' => 'mensagem',
        'password' => 'senha',
        'company_name' => 'nome da empresa',
        'url' => 'endereço do site',
        'domains' => 'domínios',
        'title' => 'título',
        'primary_color' => 'cor principal',
        'position' => 'posição',
        'role' => 'perfil',
        'max_concurrent_chats' => 'conversas simultâneas',
        'availability' => 'disponibilidade',
    ],
];
