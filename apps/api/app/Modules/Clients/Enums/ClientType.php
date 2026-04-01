<?php

namespace App\Modules\Clients\Enums;

enum ClientType: string
{
    case PessoaFisica = 'pessoa_fisica';
    case Empresa = 'empresa';
}
