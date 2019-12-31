<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-api-problem for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-api-problem/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-api-problem/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\ApiProblem\Listener;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Exception\ExceptionInterface as ViewExceptionInterface;

/**
 * RenderErrorListener
 *
 * Provides a listener on the render.error event, at high priority.
 */
class RenderErrorListener extends AbstractListenerAggregate
{
    /**
     * @var bool
     */
    protected $displayExceptions = false;

    /**
     * @param EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'onRenderError'), 100);
    }

    /**
     * @param  bool $flag
     * @return RenderErrorListener
     */
    public function setDisplayExceptions($flag)
    {
        $this->displayExceptions = (bool) $flag;
        return $this;
    }

    /**
     * Handle rendering errors
     *
     * Rendering errors are usually due to trying to render a template in
     * the PhpRenderer, when we have no templates.
     *
     * As such, report as an unacceptable response.
     *
     * @param  MvcEvent $e
     */
    public function onRenderError(MvcEvent $e)
    {
        $response    = $e->getResponse();
        $status      = 406;
        $title       = 'Not Acceptable';
        $describedBy = 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html';
        $detail      = 'Your request could not be resolved to an acceptable representation.';
        $details     = false;

        $exception   = $e->getParam('exception');
        if ($exception instanceof \Exception
            && !$exception instanceof ViewExceptionInterface
        ) {
            $code = $exception->getCode();
            if ($code >= 100 && $code <= 600) {
                $status = $code;
            } else {
                $status = 500;
            }
            $title   = 'Unexpected error';
            $detail  = $exception->getMessage();
            $details = array(
                'code'    => $exception->getCode(),
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            );
        }

        $payload = array(
            'status'      => $status,
            'title'       => $title,
            'describedBy' => $describedBy,
            'detail'      => $detail,
        );
        if ($details && $this->displayExceptions) {
            $payload['details'] = $details;
        }

        $response->getHeaders()->addHeaderLine('content-type', 'application/problem+json');
        $response->setStatusCode($status);
        $response->setContent(json_encode($payload));

        $e->stopPropagation();
    }
}
