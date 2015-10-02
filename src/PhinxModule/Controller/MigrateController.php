<?php

namespace PhinxModule\Controller;

use OAuth2\Request as Oauth2Request;
use OAuth2\Response as Oauth2Responce;
use OAuth2\Server as Oauth2Server;
use PhinxModule\Manager\PhinxWebManager;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;
use Zend\Http\Request as HttpRequest;

class MigrateController extends AbstractActionController
{
    /**
     * This is webmanaget instance
     *
     * @var $webmanager
     */
    protected $webmanager;

    /**
     * @var Oauth2Server
     */
    protected $oauth2Server;
    /**
     * Phinx
     * @var array
     */
    protected $permissionsConfig;

    public function __construct(Oauth2Server $oauth2Server, array $permissionsConfig)
    {
        $this->oauth2Server = $oauth2Server;
        $this->permissionsConfig = $permissionsConfig;
    }

    public function statusAction()
    {
        $request = $this->getRequest();

        if (! $request instanceof HttpRequest) {
            // not an HTTP request; nothing left to do
            return;
        }

        if ($request->isOptions()) {
            // OPTIONS request.
            // This is most likely a CORS attempt; as such, pass the response on.
            return $this->getResponse();
        }
        /**
         * @TODO Continue Oauth2 integration
         */
        if (!$this->oauth2Server->verifyResourceRequest(Oauth2Request::createFromGlobals(), null, $this->permissionsConfig['getstatus']) && !$this->oauth2Server->verifyResourceRequest(Oauth2Request::createFromGlobals(), null, $this->permissionsConfig['superadmin'])) // null can be access rulle
        {
            $responce = $this->getResponse();
            $oauthResponce = $this->oauth2Server->getResponse();
            return $this->showOauth2Errors($responce, $oauthResponce);
        }
var_dump($this->oauth2Server->getAccessTokenData(Oauth2Request::createFromGlobals()));
        exit;
        try
        {
            $responce = $this->getPhinxWebManager()->getStatus($this->params('migration_client'), $this->getResponse());
            if ($responce->getHeaders()->get('Content-Type') === false)
            {
                echo '<pre>';
            }
            return $responce;
        }
        catch (\Exception $e)
        {
            return new ApiProblemResponse(
                new ApiProblem(
                    404,
                    $e->getMessage()
                )
            );
        }
//        return $this->getPhinxWebManager()->getStatus($this->getResponse());
//        echo 'statusAction';
//        exit;
        /*$httpResponse = $this->getResponse();
        $httpResponse->setStatusCode(200);

        $headers = $httpResponse->getHeaders();
//        $headers->addHeaders($response->getHttpHeaders());
        $headers->addHeaderLine('Content-type', 'application/json');

        $content = json_encode(array('asd'=>'sdf','asd2'=>'sasdf'));
        $httpResponse->setContent($content);
        return $httpResponse;*/

        /* return new ApiProblemResponse(
             new ApiProblem(
                 409,
                 'asdsad asdad'
             )
         );*/
    }

    public function groupmigrateAction()
    {
        $config = $this->getServiceLocator()->get('config');
        $appenv = $config['phinx-module']['phinx-env'];
        $is_error = false;
        $error_code = null;
        $error_message = null;
        if ($appenv != 'prod')
        {
            $error_code = 409;
            $error_message = 'This "' . $appenv . '" environment not supported groupmigration';
            $is_error = true;
        }


        if ($is_error === false)
        {
            $command = 'cd ' . getcwd() . " && php public/index.php phinx groupmigrate --serverip=162.243.66.179 > /dev/null 2>/dev/null & echo $!"; //
//            var_dump(shell_exec($command));
            $json = new JsonModel();
            return $json;
        }
        else
        {
            return new ApiProblemResponse(
                new ApiProblem(
                    $error_code,
                    $error_message
                )
            );
        }
    }

    public function testAction()
    {
        echo 'testAction';
       /* $command = 'ps -p 12136 -o comm=';
        $a = shell_exec($command);
        if ($a)
        {
            echo '<pre>';
            var_dump($a);
        }
        else
        {
            echo 'asad';
        }*/

//        $comm2 = "echo Waiting...; while ps -p 22792 > /dev/null 2>/dev/null &; do echo test..; done; ls";
//        $a = shell_exec($comm2);
//        echo '<pre>';
//        echo $a;

        exit;
    }

    protected function getPhinxWebManager()
    {
        if (!$this->webmanager)
        {
            $this->webmanager = new PhinxWebManager(
                $this->getServiceLocator()->get('config'),
                $this->getServiceLocator()->get('prod-db-clients')
            );
        }

        return $this->webmanager;
    }

    private function showOauth2Errors(Response $responce, Oauth2Responce $oauth2Responce)
    {
        $headers = $responce->getHeaders();
        $headers->addHeaders($oauth2Responce->getHttpHeaders());
        $oauthStatusCode = $oauth2Responce->getStatusCode();

        $parameters       = $oauth2Responce->getParameters();
        $errorUri         = isset($parameters['error_uri'])         ? $parameters['error_uri']         : null;
        $error            = isset($parameters['error'])             ? $parameters['error']             : null;
        $errorDescription = isset($parameters['error_description']) ? $parameters['error_description'] : null;

        if(!isset($errorDescription))
        {
            $oauthStatusCode = 403;
            $errorDescription = 'Forbidden';
        }
        return new ApiProblemResponse(
            new ApiProblem(
                $oauthStatusCode,
                $errorDescription,
                $errorUri,
                $error
            )
        );
    }
}
