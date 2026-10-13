<?php
namespace App\Controllers\Igreja;

use App\Core\Response;
use App\Core\Session;
use App\Services\ConfiguracaoService;

if (!defined('MONANA')) {
    http_response_code(403);
    exit;
}

/** Configurações administrativas da igreja autenticada. */
final class ConfiguracaoController extends BaseIgrejaController
{
    public function index(): Response
    {
        $guard = $this->guardSettings();
        if ($guard instanceof Response) {
            return $guard;
        }
        $configuration = (new ConfiguracaoService())->encontrar($this->churchId);
        if ($configuration === null) {
            return Response::text('Não foi encontrada a configuração da igreja autenticada.', 404);
        }
        $message = (string) Session::get('flash_configuracoes', '');
        Session::remove('flash_configuracoes');
        return $this->renderChurchModule('igreja/configuracoes/index', [
            'configuracao' => $configuration,
            'erros' => [],
            'mensagem' => $message,
        ], 'Configurações', 'configuracoes');
    }

    public function actualizar(): Response
    {
        $guard = $this->guardSettings();
        if ($guard instanceof Response) {
            return $guard;
        }
        $input = [
            'nome_exibicao' => $this->request->post('nome_exibicao', ''),
            'subtitulo' => $this->request->post('subtitulo', ''),
            'rodape' => $this->request->post('rodape', ''),
            'versiculo' => $this->request->post('versiculo', ''),
            'valor_cota' => $this->request->post('valor_cota', ''),
            'periodicidade_cota' => $this->request->post('periodicidade_cota', ''),
            'inicio_cotas' => $this->request->post('inicio_cotas', ''),
            'inicio_contribuicoes' => $this->request->post('inicio_contribuicoes', ''),
        ];
        $result = (new ConfiguracaoService())->actualizar(
            $this->churchId,
            (int) ($this->actor['id'] ?? 0),
            $input,
            $this->request
        );
        if (empty($result['sucesso'])) {
            $configuration = array_merge((array) (new ConfiguracaoService())->encontrar($this->churchId), (array) ($result['dados'] ?? $input));
            return $this->renderChurchModule('igreja/configuracoes/index', [
                'configuracao' => $configuration,
                'erros' => (array) ($result['erros'] ?? []),
                'mensagem' => (string) ($result['erro'] ?? ''),
            ], 'Configurações', 'configuracoes', 422);
        }
        Session::set('flash_configuracoes', 'Configurações guardadas para esta igreja.');
        return Response::redirect(url('configuracoes'), 303);
    }

    private function guardSettings(): ?Response
    {
        return $this->authorizeModule('settings', ['admin_igreja']);
    }
}
