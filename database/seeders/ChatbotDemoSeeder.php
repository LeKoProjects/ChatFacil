<?php

namespace Database\Seeders;

use App\Enums\BotNodeType;
use App\Models\BotNode;
use App\Models\Chatbot;
use App\Models\Company;
use App\Models\FaqEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Chatbot de demonstração da "Empresa Demo" (suporte de TI): menu de opções
 * com respostas prontas, transferências por setor e perguntas frequentes.
 *
 * Para mudar o conteúdo, edite MENU e FAQ abaixo. Cada item do menu é:
 *   'label'    texto do botão
 *   'type'     menu (abre as opções de 'children') | answer (resposta pronta) | handoff (transfere)
 *   'message'  resposta / pergunta do submenu / mensagem antes de transferir
 *   'tag'      setor mostrado ao atendente (só handoff)
 *
 * Não altera nada se a empresa já tiver um chatbot configurado. Para recriar:
 * php artisan migrate:fresh --seed (apaga o banco de desenvolvimento).
 */
class ChatbotDemoSeeder extends Seeder
{
    private const MENU = [
        [
            'label' => 'Suporte técnico',
            'type' => 'menu',
            'message' => 'Certo! Qual é o problema?',
            'children' => [
                [
                    'label' => 'Internet sem conexão',
                    'type' => 'answer',
                    'message' => "Vamos tentar resolver juntos:\n1. Desligue o roteador da tomada por 30 segundos.\n2. Ligue de novo e aguarde 2 minutos até as luzes estabilizarem.\n3. Teste em outro aparelho (celular, por exemplo).\n\nSe só um computador estiver sem internet, reinicie esse computador.",
                ],
                [
                    'label' => 'Impressora',
                    'type' => 'menu',
                    'message' => 'O que está acontecendo com a impressora?',
                    'children' => [
                        [
                            'label' => 'Não imprime',
                            'type' => 'answer',
                            'message' => "Confira:\n1. A impressora está ligada e sem luz de erro?\n2. Tem papel e tinta/toner?\n3. No computador, abra \"Impressoras e scanners\", escolha a impressora e clique em \"Abrir fila\". Cancele os documentos travados e tente de novo.",
                        ],
                        [
                            'label' => 'Papel enroscado',
                            'type' => 'answer',
                            'message' => 'Desligue a impressora, abra as tampas indicadas no manual e puxe o papel devagar, no sentido da saída. Não use objetos cortantes. Depois ligue de novo e imprima uma página de teste.',
                        ],
                        [
                            'label' => 'Instalar impressora nova',
                            'type' => 'handoff',
                            'message' => 'A instalação é feita pela nossa equipe. Vou chamar um técnico para combinar com você.',
                            'tag' => 'Suporte',
                        ],
                    ],
                ],
                [
                    'label' => 'Computador lento',
                    'type' => 'answer',
                    'message' => "Algumas dicas rápidas:\n1. Reinicie o computador (não apenas \"desligar a tela\").\n2. Feche programas e abas do navegador que não estiver usando.\n3. Verifique se há atualizações pendentes do Windows e deixe instalar.\n\nSe continuar lento, fale com um técnico.",
                ],
                [
                    'label' => 'E-mail',
                    'type' => 'menu',
                    'message' => 'Qual o problema com o e-mail?',
                    'children' => [
                        [
                            'label' => 'Esqueci a senha',
                            'type' => 'answer',
                            'message' => 'Na tela de login do e-mail, clique em "Esqueci minha senha" e siga o link enviado para o seu e-mail ou celular de recuperação.',
                        ],
                        [
                            'label' => 'Configurar no celular',
                            'type' => 'answer',
                            'message' => 'No celular, abra o app Outlook (ou Gmail), toque em "Adicionar conta" e informe seu e-mail e senha corporativos. A configuração é automática.',
                        ],
                        [
                            'label' => 'Não recebo e-mails',
                            'type' => 'handoff',
                            'message' => 'Isso precisa ser verificado no servidor. Vou chamar um técnico.',
                            'tag' => 'Suporte',
                        ],
                    ],
                ],
                [
                    'label' => 'Outro problema',
                    'type' => 'handoff',
                    'message' => 'Sem problemas, vou chamar um técnico para você.',
                    'tag' => 'Suporte',
                ],
            ],
        ],
        [
            'label' => 'Financeiro',
            'type' => 'menu',
            'message' => 'Sobre o que é?',
            'children' => [
                [
                    'label' => 'Segunda via de boleto',
                    'type' => 'answer',
                    'message' => 'Acesse o portal do cliente, menu Financeiro > Boletos, e clique em "2ª via". O boleto atualizado é gerado na hora.',
                ],
                [
                    'label' => 'Formas de pagamento',
                    'type' => 'answer',
                    'message' => 'Aceitamos boleto, Pix e cartão de crédito. O Pix é compensado na hora; o boleto, em até 2 dias úteis.',
                ],
                [
                    'label' => 'Nota fiscal',
                    'type' => 'answer',
                    'message' => 'A nota fiscal é enviada por e-mail no dia do pagamento e também fica no portal do cliente, menu Financeiro > Notas fiscais.',
                ],
                [
                    'label' => 'Falar com o financeiro',
                    'type' => 'handoff',
                    'tag' => 'Financeiro',
                ],
            ],
        ],
        [
            'label' => 'Comercial / orçamento',
            'type' => 'menu',
            'message' => 'Que bom ter você por aqui! Como podemos ajudar?',
            'children' => [
                [
                    'label' => 'Serviços que oferecemos',
                    'type' => 'answer',
                    'message' => "Atendemos empresas com:\n• Suporte técnico (remoto e presencial)\n• Redes e Wi-Fi\n• Servidores e backup\n• E-mail corporativo e Microsoft 365\n• Câmeras e controle de acesso",
                ],
                [
                    'label' => 'Pedir orçamento',
                    'type' => 'handoff',
                    'message' => 'Ótimo! Vou chamar alguém do comercial para entender o que você precisa.',
                    'tag' => 'Comercial',
                ],
            ],
        ],
    ];

    private const FAQ = [
        ['Qual o horário de atendimento?', 'Atendemos de segunda a sexta, das 8h às 18h. Chamados urgentes de clientes com contrato têm plantão 24h.', 'horario hora abre fecha funciona expediente plantao'],
        ['Como emitir a segunda via do boleto?', 'Acesse o portal do cliente, menu Financeiro > Boletos, e clique em "2ª via".', 'fatura pagamento cobranca vencido'],
        ['Quais as formas de pagamento?', 'Aceitamos boleto, Pix e cartão de crédito.', 'pix cartao credito pagar'],
        ['Onde encontro a nota fiscal?', 'A nota fiscal é enviada por e-mail no dia do pagamento e fica no portal do cliente, menu Financeiro > Notas fiscais.', 'nf nfe nota'],
        ['Vocês atendem presencialmente?', 'Sim, com agendamento. Fale com um atendente para marcar a visita técnica.', 'visita tecnico local presencial ir'],
        ['Qual o prazo para atender um chamado?', 'Chamados comuns são atendidos em até 4 horas úteis; urgentes, em até 1 hora.', 'prazo demora tempo sla urgente'],
        ['Minha impressora não imprime, o que faço?', 'Confira se está ligada, com papel e tinta. No computador, abra "Impressoras e scanners", clique em "Abrir fila" e cancele os documentos travados.', 'impressora imprimir impressao'],
        ['Minha internet caiu, o que faço?', 'Desligue o roteador da tomada por 30 segundos, ligue de novo e aguarde 2 minutos. Se não voltar, fale com um técnico.', 'internet wifi rede conexao caiu'],
    ];

    public function run(TenantContext $tenant): void
    {
        $company = Company::query()->where('slug', 'empresa-demo')->first()
            ?? Company::query()->where('name', 'Empresa Demo')->first();

        if (! $company) {
            return;
        }

        $tenant->runAs($company->id, function (): void {
            if (Chatbot::query()->exists()) {
                return;
            }

            Chatbot::create([...Chatbot::DEFAULTS, 'is_enabled' => true]);

            $this->createNodes(self::MENU, null);

            foreach (self::FAQ as [$question, $answer, $keywords]) {
                FaqEntry::create(['question' => $question, 'answer' => $answer, 'keywords' => $keywords]);
            }
        });
    }

    /** @param  list<array<string, mixed>>  $items */
    private function createNodes(array $items, ?BotNode $parent): void
    {
        foreach ($items as $order => $item) {
            $node = BotNode::create([
                'parent_id' => $parent?->id,
                'type' => BotNodeType::from($item['type']),
                'label' => $item['label'],
                'message' => $item['message'] ?? null,
                'handoff_tag' => $item['tag'] ?? null,
                'sort_order' => $order + 1,
            ]);

            $this->createNodes($item['children'] ?? [], $node);
        }
    }
}
