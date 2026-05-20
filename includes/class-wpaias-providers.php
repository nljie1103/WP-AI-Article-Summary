<?php
/**
 * 服务商与模型预设清单（三级联动数据源）。
 *
 * @package WP_AI_Article_Summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPAIAS_Providers
 */
class WPAIAS_Providers {

	/**
	 * 获取全部服务商配置（预设列表）。
	 *
	 * 字段说明：
	 *  - label    : 显示名称
	 *  - endpoint : 默认接口地址（chat/completions 风格）
	 *  - format   : 请求体格式（openai / gemini / claude / custom）
	 *  - models   : 内置模型清单
	 *  - auth     : 鉴权类型（bearer / header_key / url_key / x-api-key）
	 *  - auth_header : 自定义鉴权 header 名（可选）
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'openai' => array(
				'label'    => 'OpenAI',
				'endpoint' => 'https://api.openai.com/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'gpt-4o',
					'gpt-4o-mini',
					'gpt-4-turbo',
					'gpt-4',
					'gpt-3.5-turbo',
				),
			),
			'gemini' => array(
				'label'    => 'Gemini（Google）',
				'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
				'format'   => 'gemini',
				'auth'     => 'url_key',
				'models'   => array(
					'gemini-1.5-pro',
					'gemini-1.5-flash',
					'gemini-1.5-flash-8b',
					'gemini-pro',
					'gemini-ultra',
				),
			),
			'deepseek' => array(
				'label'    => 'DeepSeek',
				'endpoint' => 'https://api.deepseek.com/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'deepseek-chat',
					'deepseek-reasoner',
					'deepseek-coder',
				),
			),
			'volcengine' => array(
				'label'    => '火山方舟（字节）',
				'endpoint' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'doubao-pro-32k',
					'doubao-pro-128k',
					'doubao-pro-4k',
					'doubao-lite-32k',
					'doubao-lite-128k',
					'doubao-lite-4k',
				),
			),
			'kimi' => array(
				'label'    => 'Kimi（月之暗面）',
				'endpoint' => 'https://api.moonshot.cn/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'kimi-latest',
					'moonshot-v1-8k',
					'moonshot-v1-32k',
					'moonshot-v1-128k',
				),
			),
			'openrouter' => array(
				'label'    => 'OpenRouter',
				'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'openai/gpt-4o',
					'openai/gpt-4o-mini',
					'anthropic/claude-3.5-sonnet',
					'anthropic/claude-3-haiku',
					'google/gemini-pro-1.5',
					'deepseek/deepseek-chat',
					'meta-llama/llama-3.1-70b-instruct',
					'mistralai/mistral-large',
				),
			),
			'claude' => array(
				'label'    => 'Claude（Anthropic）',
				'endpoint' => 'https://api.anthropic.com/v1/messages',
				'format'   => 'claude',
				'auth'     => 'x-api-key',
				'models'   => array(
					'claude-3-5-sonnet-20241022',
					'claude-3-5-haiku-20241022',
					'claude-3-opus-20240229',
					'claude-3-sonnet-20240229',
					'claude-3-haiku-20240307',
				),
			),
			'qwen' => array(
				'label'    => '通义千问（阿里）',
				'endpoint' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'qwen-max',
					'qwen-plus',
					'qwen-turbo',
					'qwen-long',
					'qwen2.5-72b-instruct',
					'qwen2.5-32b-instruct',
				),
			),
			'spark' => array(
				'label'    => '讯飞星火',
				'endpoint' => 'https://spark-api-open.xf-yun.com/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'spark-lite',
					'spark-pro',
					'spark-pro-128k',
					'spark-max',
					'spark-ultra',
					'4.0Ultra',
				),
			),
			'glm' => array(
				'label'    => '智谱 GLM',
				'endpoint' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'glm-4-plus',
					'glm-4-0520',
					'glm-4',
					'glm-4-air',
					'glm-4-flash',
					'glm-3-turbo',
				),
			),
			'ai360' => array(
				'label'    => '360 智脑',
				'endpoint' => 'https://api.360.cn/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'360gpt-pro',
					'360gpt-turbo',
					'360gpt-turbo-responsibility-8k',
				),
			),
			'ernie' => array(
				'label'    => '百度文心一言',
				'endpoint' => 'https://qianfan.baidubce.com/v2/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'ernie-4.0-8k',
					'ernie-4.0-turbo-8k',
					'ernie-3.5-8k',
					'ernie-speed-128k',
					'ernie-lite-8k',
				),
			),
			'doubao' => array(
				'label'    => '豆包（字节）',
				'endpoint' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'doubao-pro-32k',
					'doubao-pro-128k',
					'doubao-lite-32k',
					'doubao-lite-128k',
					'doubao-1.5-pro-32k',
					'doubao-1.5-lite-32k',
				),
			),
			'mistral' => array(
				'label'    => 'Mistral',
				'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'mistral-large-latest',
					'mistral-small-latest',
					'open-mistral-7b',
					'open-mixtral-8x7b',
					'open-mixtral-8x22b',
				),
			),
			'grok' => array(
				'label'    => 'Grok（xAI）',
				'endpoint' => 'https://api.x.ai/v1/chat/completions',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(
					'grok-2-latest',
					'grok-2',
					'grok-2-mini',
					'grok-beta',
					'grok-1',
				),
			),
			'custom' => array(
				'label'    => '自定义接口',
				'endpoint' => '',
				'format'   => 'openai',
				'auth'     => 'bearer',
				'models'   => array(),
			),
		);
	}

	/**
	 * 获取指定服务商配置。
	 *
	 * @param string $key 服务商 key。
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * 输出给前端 JS 的 JSON 模型映射。
	 *
	 * @return array
	 */
	public static function js_map() {
		$map = array();
		foreach ( self::all() as $key => $cfg ) {
			$map[ $key ] = array(
				'label'    => $cfg['label'],
				'endpoint' => $cfg['endpoint'],
				'models'   => $cfg['models'],
			);
		}
		return $map;
	}
}
