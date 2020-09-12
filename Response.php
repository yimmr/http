<?php
namespace Impack\Http;

use ArrayObject;
use Impack\Contracts\Support\Arrayable;
use Impack\Contracts\Support\Jsonable;
use JsonSerializable;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Response extends SymfonyResponse
{
    protected $original;

    public function __construct($content = '', $status = 200, array $headers = [])
    {
        $this->headers = new ResponseHeaderBag($headers);
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setProtocolVersion('1.0');
    }

    /** 响应前的内容 */
    public function original()
    {
        $original = $this->original;
        return $original instanceof self ? $original->{__FUNCTION__}() : $original;
    }

    /**
     * 设置响应头
     * @param array|HeaderBag|string
     */
    public function header($header, $values = '', $replace = true)
    {
        if (\is_string($header)) {
            $this->headers->set($header, $values, $replace);
            return $this;
        }

        if ($header instanceof HeaderBag) {
            $header = $header->all();
        }

        foreach ($header as $key => $value) {
            $this->headers->set($key, $value);
        }

        return $this;
    }

    /** 设置响应内容，字符串或可转JSON的对象数组 */
    public function setContent($content)
    {
        $this->original = $content;

        if ($this->shouldBeJson($content)) {
            $this->header('Content-Type', 'application/json');
            $content = $this->morphToJson($content);
        }

        parent::setContent($content);

        return $this;
    }

    /** 设置cookie */
    public function cookie($cookie)
    {
        $this->headers->setCookie($cookie);
        return $this;
    }

    /** 确定数据是否可转成json格式 */
    protected function shouldBeJson($content)
    {
        return $content instanceof Arrayable ||
        $content instanceof Jsonable ||
        $content instanceof ArrayObject ||
        $content instanceof JsonSerializable ||
        is_array($content);
    }

    /** 内容转为JSON格式 */
    protected function morphToJson($content)
    {
        if ($content instanceof Jsonable) {
            return $content->toJson();
        } elseif ($content instanceof Arrayable) {
            return json_encode($content->toArray());
        }
        return json_encode($content, JSON_UNESCAPED_UNICODE);
    }
}
