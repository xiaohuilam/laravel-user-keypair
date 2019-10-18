<?php
namespace Xiaohuilam\LaravelUserKeypair\Http\Middleware;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

abstract class BaseMiddleware
{
    /**
     * 签名方法
     *
     * @param string $resource 资源，一般是访问的路径文件
     * @param array $parameters 参数
     * @param string $accessKeyId
     * @param string $accessKeySecret
     *
     * @return array 带签名的参数
     */
    abstract public function sign($resource, $parameters, $accessKeyId, $accessKeySecret);

    /**
     * 获取AccessKey模型类名
     *
     * @return string
     */
    protected function getAccessKeyClass()
    {
        return "\\App\\Models\\AccessKey";
    }

    /**
     * AccessKey 验签
     *
     * @param  \Illuminate\Http\Request  $request
     * @param \Closure $next
     * @return string
     */
    public function handle($request, $next)
    {
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->headers->set('Accept', 'application/json');

        $request->validate([
            'accessKeyId' => 'required|string',
            'sign' => 'required|string',
            'nonce' => 'required|string',
            'timestamp' => 'required|date_format:Y-m-d\TH:i:s\Z',
        ]);

        $api = $request->route()->getName();
        $nonce = $request->input('nonce');
        $timestamp = $request->input('timestamp');

        if (abs((Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $timestamp, config('app.timezone')))->getTimestamp() - now()->getTimestamp()) > 15 * 60) {
            abort(400, 'Timestamp\'s deviation must not be greater than 15 minutes');
        }

        $accessKeyId = $request->input('accessKeyId');
        $resource = '/' . $request->path();
        $parameters = $request->except(['sign',]);
        ksort($parameters);

        $key = app($this->getAccessKeyClass())->where('access_key_id', $accessKeyId)->first();
        if (!$key) {
            abort(404, 'AccessKey does not exists');
        }
        $accessKeySecret = $key->access_key_secret;

        $sign = data_get($this->sign($resource, $parameters, $accessKeyId, $accessKeySecret), 'sign');
        if ($sign !== $request->input('sign')) {
            abort(403, 'Bad signature' . (config('app.env') != 'production' ? (' ' . $sign) : ''));
        }

        if ($key->user->deactivated()) {
            abort(403, 'User deactivated');
        }

        if (Cache::has('nonce:' . $nonce . ':keyid:' . $accessKeyId)) {
            if (Cache::has('nonce:' . $nonce . ':keyid:' . $accessKeyId . ':api:' . $api) && Cache::get('nonce:' . $nonce . ':keyid:' . $accessKeyId . ':api:' . $api) == $sign) {
                return response(Cache::get('nonce:' . $nonce . ':keyid:' . $accessKeyId . ':response'));
            }
            abort(400, 'Conflicting nonce');
        }

        $response = $next($request);
        Cache::put('nonce:' . $nonce . ':keyid:' . $accessKeyId, 1, 1440 * 7);
        Cache::put('nonce:' . $nonce . ':keyid:' . $accessKeyId . ':api:' . $api, $sign, 1440 * 7);
        Cache::put('nonce:' . $nonce . ':keyid:' . $accessKeyId . ':response', $response->getContent(), 1440 * 7);

        return $response;
    }
}
