<?php
namespace Codeception\Lib\Connector;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as DomRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Laravel5 extends Client implements HttpKernelInterface
{

    /**
     * @var Application
     */
    private $app;

    /**
     * @var HttpKernelInterface
     */
    private $httpKernel;

    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->httpKernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
        $this->httpKernel->bootstrap();
        $this->app->boot();

        parent::__construct($this);
    }

    /**
     * Handle a request.
     *
     * @param Request $request
     * @param int $type
     * @param bool $catch
     * @return Response
     */
    public function handle(DomRequest $request, $type = self::MASTER_REQUEST, $catch = true) {
        $request = Request::createFromBase($request);
        $request->enableHttpMethodParameterOverride();

        $this->app->bind('request', $request);

        return $this->httpKernel->handle($request);
    }
}
