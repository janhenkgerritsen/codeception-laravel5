<?php
namespace Codeception\Lib\Connector;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as DomRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Laravel5 extends Client implements HttpKernelInterface
{

    /**
     * @var HttpKernelInterface
     */
    private $httpKernel;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $httpKernel
     */
    public function __construct(Kernel $httpKernel)
    {
        $this->httpKernel = $httpKernel;

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

        return $this->httpKernel->handle($request);
    }
}
