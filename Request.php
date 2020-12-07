<?php
namespace Impack\Http;

use ArrayAccess;
use Impack\Support\Arr;
use Impack\Support\Str;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest implements ArrayAccess
{
    protected $json;

    /** 请求是否要预加载 */
    public function prefetch()
    {
        return strcasecmp($this->server->get('HTTP_X_MOZ'), 'prefetch') === 0 ||
        strcasecmp($this->headers->get('Purpose'), 'prefetch') === 0;
    }

    /** 客户端IP */
    public function ip()
    {
        return $this->getClientIp();
    }

    /** 所有可能的客户端IP数组 */
    public function ips()
    {
        return $this->getClientIps();
    }

    /** HTTP客户端运行的浏览器信息 */
    public function userAgent()
    {
        return $this->headers->get('User-Agent');
    }

    /** 请求方法 */
    public function method()
    {
        return $this->getMethod();
    }

    /** 首页URL */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost() . $this->getBaseUrl(), '/');
    }

    /** 当前请求URL(无查询字符串) */
    public function url()
    {
        return rtrim(preg_replace('/\?.*/', '', $this->getUri()), '/');
    }

    /** 当前请求完整URL */
    public function fullUrl()
    {
        $query    = $this->getQueryString();
        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';
        return $query ? $this->url() . $question . $query : $this->url();
    }

    /** 当前URL生成带查询字符串的URL */
    public function addUrlArg(array $query)
    {
        $question = $this->getBaseUrl() . $this->getPathInfo() === '/' ? '/?' : '?';
        return count($this->query()) > 0
        ? $this->url() . $question . Arr::query(array_merge($this->query(), $query))
        : $this->fullUrl() . $question . Arr::query($query);
    }

    /** 当前URL目录部分 */
    public function path()
    {
        return trim($this->getPathInfo(), '/') ?: '/';
    }

    /** 解码当前URL目录部分 */
    public function decodedPath()
    {
        return rawurldecode($this->path());
    }

    /** 当前URL目录部分根据 '/' 拆分成数组 */
    public function segments()
    {
        $segments = explode('/', $this->decodedPath());
        return array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));
    }

    /** 当前URL目录部分是否与指定正则模式匹配 */
    public function is(...$patterns)
    {
        $path = $this->decodedPath();
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }
        return false;
    }

    /** 请求参数与自定义值合并 */
    public function merge(array $input)
    {
        $this->getInputSource()->add($input);
        return $this;
    }

    /** 替换当前请求参数 */
    public function replace(array $input)
    {
        $this->getInputSource()->replace($input);
        return $this;
    }

    /** 是否是AJAX请求 */
    public function ajax()
    {
        return $this->isXmlHttpRequest();
    }

    /** 是否是PJAX请求 */
    public function pjax()
    {
        return $this->headers->get('X-PJAX') == true;
    }

    /** 获取当前请求参数值 */
    public function query($key = null, $default = null)
    {
        return is_null($key) ? $this->query->all() : $this->query->get($key, $default);
    }

    /** 当前请求所有参数值 */
    public function input()
    {
        return $this->getInputSource()->all() + $this->query();
    }

    /** 客户端上传的所有文件数组 */
    public function allFiles()
    {
        return $this->files->all();
    }

    /** 当前请求所有类型的参数值（files+input） */
    public function all($keys = null)
    {
        $input = array_replace_recursive($this->input(), $this->allFiles());

        if (!$keys) {
            return $input;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($input, $key));
        }

        return $results;
    }

    /** 请求正文是否是JSON格式 */
    public function isJson()
    {
        return Str::contains($this->headers->get('CONTENT_TYPE'), ['/json', '+json']);
    }

    /** 解析JSON类型的正文 */
    public function json($key = null, $default = null)
    {
        if (!isset($this->json)) {
            $this->json = new ParameterBag((array) json_decode($this->getContent(), true));
        }
        return is_null($key) ? $this->json : Arr::get($this->json->all(), $key, $default);
    }

    /** 工厂模式，创建一个请求对象 */
    public static function capture()
    {
        return static::createFrom(SymfonyRequest::createFromGlobals());
    }

    protected static function createFrom(SymfonyRequest $request)
    {
        if ($request instanceof static ) {
            return $request;
        }
        $_request = (new static )->duplicate(
            $request->query->all(), $request->request->all(), $request->attributes->all(),
            $request->cookies->all(), static::filterFiles($request->files->all()), $request->server->all()
        );
        $_request->headers->replace($request->headers->all());
        $_request->content = $request->content;
        $_request->request = $_request->getInputSource();
        return $_request;
    }

    /** 过滤文件参数数组，删除所有空值 */
    protected static function filterFiles($files)
    {
        if (!$files) {
            return;
        }

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $files[$key] = static::filterFiles($files[$key]);
            }
            if (empty($files[$key])) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    /** 根据请求类型解析并返回请求内容 */
    protected function getInputSource()
    {
        if ($this->isJson()) {
            return $this->json();
        }
        return in_array($this->getRealMethod(), ['GET', 'HEAD']) ? $this->query : $this->request;
    }

    public function offsetExists($offset)
    {
        return Arr::has($this->all(), $offset);
    }

    public function offsetGet($offset)
    {
        return Arr::get($this->all(), $offset, '');
    }

    public function offsetSet($offset, $value)
    {
        $this->getInputSource()->set($offset, $value);

    }

    public function offsetUnset($offset)
    {
        $this->getInputSource()->remove($offset);
    }

    public function __isset($key)
    {
        return !is_null($this->__get($key));
    }

    public function __get($key)
    {
        return Arr::get($this->all(), $key, $key);
    }
}