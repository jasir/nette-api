<?php

namespace Tomaj\NetteApi\Presenters;

use Nette\Application\Responses\JsonResponse;
use Nette\Application\UI\Presenter;
use Nette\Http\Response;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Logger\ApiLoggerInterface;
use Tomaj\NetteApi\Misc\IpDetectorInterface;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tracy\Debugger;
use Exception;

/**
 * @property-read \Nette\DI\Container $context
 */
class ApiPresenter extends Presenter
{
    /**
     * @var  ApiDecider @inject
     */
    public $apiDecider;

    /**
     * @var  IpDetectorInterface @inject
     */
    public $ipDetector;

    /**
     * CORS header settings
     *
     * Available values:
     *   'auto'  - send back header Access-Control-Allow-Origin with domain that made request
     *   '*'     - send header with '*' - this will workf fine if you dont need to send cookies via ajax calls to api
     *             with jquery $.ajax with xhrFields: { withCredentials: true } settings
     *   'off'   - will not send any CORS header
     *   other   - any other value will be send in Access-Control-Allow-Origin header
     *
     * @var string
     */
    protected $corsHeader = '*';

    /**
     * Presenter startup method
     *
     * @return void
     */
    public function startup()
    {
        parent::startup();
        $this->autoCanonicalize = false;
    }

    /**
     * Set cors header
     *
     * See description to property $corsHeader for valid inputs
     *
     * @param string $corsHeader
     */
    public function setCorsHeader($corsHeader)
    {
        $this->corsHeader = $corsHeader;
    }

    /**
     * Nette render default method
     *
     * @return void
     */
    public function renderDefault()
    {
        $start = microtime(true);

        $this->sendCorsHeaders();

        $hand = $this->getHandler();
        $handler = $hand['handler'];
        $authorization = $hand['authorization'];

        if ($this->checkAuth($authorization) === false) {
            return;
        }

        $params = $this->processParams($handler);
        if ($params === false) {
            return;
        }

        try {
            $response = $handler->handle($params);
            $code = $response->getCode();
        } catch (Exception $exception) {
            $response = new JsonApiResponse(500, ['status' => 'error', 'message' => 'Internal server error']);
            $code = $response->getCode();
            Debugger::log($exception, Debugger::EXCEPTION);
        }

        $end = microtime(true);

        if ($this->context->hasService('apiLogger')) {
            $this->logRequest($this->context->getService('apiLogger'), $code, $end - $start);
        }

        // output to nette
        $this->getHttpResponse()->setCode($code);
        $this->sendResponse($response);
    }

    /**
     * Get handler information triplet (endpoint, handler, authorization)
     *
     * @return array
     */
    private function getHandler()
    {
        return $this->apiDecider->getApiHandler(
            $this->getRequest()->getMethod(),
            $this->params['version'],
            $this->params['package'],
            $this->params['apiAction']
        );
    }

    /**
     * Check authorization
     *
     * @param ApiAuthorizationInterface  $authorization
     *
     * @return bool
     */
    private function checkAuth(ApiAuthorizationInterface $authorization)
    {
        if (!$authorization->authorized()) {
            $this->getHttpResponse()->setCode(Response::S403_FORBIDDEN);
            $this->sendResponse(new JsonResponse(['status' => 'error', 'message' => $authorization->getErrorMessage()]));
            return false;
        }
        return true;
    }

    /**
     * Process input parameters
     *
     * @param ApiHandlerInterface   $handler
     *
     * @return array|bool
     */
    private function processParams(ApiHandlerInterface $handler)
    {
        $paramsProcessor = new ParamsProcessor($handler->params());
        if ($paramsProcessor->isError()) {
            $this->getHttpResponse()->setCode(Response::S500_INTERNAL_SERVER_ERROR);
            $this->sendResponse(new JsonResponse(['status' => 'error', 'message' => 'wrong input']));
            return false;
        }
        return $paramsProcessor->getValues();
    }

    /**
     * Log request
     *
     * @param ApiLoggerInterface  $logger
     * @param integer             $code
     * @param double              $elapsed
     *
     * @return void
     */
    private function logRequest(ApiLoggerInterface $logger, $code, $elapsed)
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        }

        $requestHeaders = '';
        foreach ($headers as $key => $value) {
            $requestHeaders .= "$key: $value\n";
        }

        $logger->log(
            $code,
            $this->getRequest()->getMethod(),
            $requestHeaders,
            filter_input(INPUT_SERVER, 'REQUEST_URI'),
            $this->ipDetector->getRequestIp(),
            filter_input(INPUT_SERVER, 'HTTP_USER_AGENT'),
            ($elapsed) * 1000
        );
    }

    protected function sendCorsHeaders()
    {
        if ($this->corsHeader == 'auto') {
            $domain = $this->getRequestDomain();
            if ($domain !== false) {
                $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', $domain);
                $this->getHttpResponse()->addHeader('Access-Control-Allow-Credentials', 'true');
            }
            return;
        }

        if ($this->corsHeader == '*') {
            $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', '*');
            return;
        }

        if ($this->corsHeader != 'off') {
            $this->getHttpResponse()->addHeader('Access-Control-Allow-Origin', $this->corsHeader);
        }
    }

    private function getRequestDomain()
    {
        if (filter_input(INPUT_SERVER, 'HTTP_REFERER')) {
            $refererParsedUrl = parse_url(filter_input(INPUT_SERVER, 'HTTP_REFERER'));
            if (isset($refererParsedUrl['scheme']) && isset($refererParsedUrl['host'])) {
                return $refererParsedUrl['scheme'] . '://' . $refererParsedUrl['host'];
            }
        }
        return false;
    }
}
