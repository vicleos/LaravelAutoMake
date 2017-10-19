<?php namespace App\Helpers;

/**
 * Class     AutoMakeFileParser
 *
 * @package  App\Helpers
 * @author   Vicleos <510331882@qq.com>
 */
class AutoMakeFileParser
{
	/* ------------------------------------------------------------------------------------------------
	 |  Properties
	 | ------------------------------------------------------------------------------------------------
	 */
	/**
	 * Parsed data.
	 *
	 * @var array
	 */
	protected static $parsed = [];

	/**
	 * 匹配类型为 explode
	 */
	const TYPE_EXPLODE = 'explode';

	/**
	 * 匹配类型为正则
	 */
	const TYPE_PREG = 'preg';

	/* ------------------------------------------------------------------------------------------------
	 |  Main Functions
	 | ------------------------------------------------------------------------------------------------
	 */
	/**
	 * Parse file content.
	 *
	 * @param  string  $raw
	 *
	 * @return array
	 */
	public static function parse($raw)
	{
		self::$parsed          = [];
		list($headings, $data) = self::parseRawData($raw);

		if ( ! is_array($headings)) {
			return self::$parsed;
		}

		foreach ($headings as $key => $heading) {

			self::$parsed[] = [
				'type'  => $heading,
				'intro'  => self::parseIntro($heading, $data[$key])
			];
		};

		unset($headings, $data);

		return self::$parsed;
	}

	/* ------------------------------------------------------------------------------------------------
	 |  Other Functions
	 | ------------------------------------------------------------------------------------------------
	 */
	/**
	 * Parse raw data.
	 *
	 * @param  string  $raw
	 *
	 * @return array
	 */
	private static function parseRawData($raw)
	{
		// 匹配 - - 中的内容
		$pattern = "/-(.*?)-/";
		preg_match_all($pattern, $raw, $headings);
		$data    = preg_split($pattern, $raw);

		if ($data[0] < 1) {
			$trash = array_shift($data);
			unset($trash);
		}
		// 清除第一个数组，-xxx-,只需要第二个就可以了
		array_shift($headings);

		return [end($headings), $data];
	}

	/**
	 * 解析对应类型的内容到数组
	 * 路由，控制器，仓库，服务
	 * @param $type
	 * @param $introRaw
	 * @return string
	 */
	private static function parseIntro($type, $introRaw)
	{
		switch ($type){
			case 'route':
				return self::parseRouteIntro($introRaw);
				break;
			case 'ctrl':
				return self::parseCtrlIntro($introRaw);
				break;
			case 'res':
				return self::parseResIntro($introRaw);
				break;
			case 'srv':
				return self::parseSevIntro($introRaw);
				break;
			case 'table':
				return self::parseTableIntro($introRaw);
				break;
			default:
				return [];
				break;
		}
	}

	private static function parseRouteIntro($introRaw)
	{
		$pattern = "/(.*?),/";
		preg_match_all($pattern, $introRaw, $routes);
		array_shift($routes);

		$routeUrlPattern = "/(.*?)=>/";

		$routes = end($routes);
		$parseRoutes = [];

		foreach ($routes as $row){
			// 清除制表符
			$row = preg_replace('/[\t\r\n\s]/', '', $row);
			preg_match_all($routeUrlPattern, $row, $routeUrl);
			$routeActionAndName = preg_split($routeUrlPattern, $row);

			array_shift($routeUrl);
			$routeUrl = current(end($routeUrl));

			if ($routeActionAndName[0] < 1) {
				$trash = array_shift($routeActionAndName);
				unset($trash);
			}

			list($ctrlAndAction, $routeName) = explode('->', current($routeActionAndName));

			list($ctrl, $action) = explode('@', $ctrlAndAction);

			$parseRoutes[] = [
				'url' => $routeUrl,
				'controller' => $ctrl,
				'action' => $action,
				'name' => $routeName
			];

		}

		return $parseRoutes;
	}

	private static function parseCtrlIntro($introRaw)
	{
		return [];
	}

	/**
	 * 解析出 仓库 相关列表
	 * @param $introRaw
	 * @return array
	 */
	private static function parseResIntro($introRaw)
	{
		$rst = self::pregRaw(self::TYPE_EXPLODE, ',', $introRaw);
		return array_filter($rst);
	}

	/**
	 * 解析出 服务 相关列表
	 * @param $introRaw
	 * @return array
	 */
	private static function parseSevIntro($introRaw)
	{
		$rst = array_filter(self::pregRaw(self::TYPE_EXPLODE, ',', $introRaw));
		return $rst;
	}

	/**
	 * 解析出 数据表 相关列表
	 * @param $introRaw
	 * @return array
	 */
	private static function parseTableIntro($introRaw)
	{
		$rst = [];
		$baseParse = array_filter(self::pregRaw(self::TYPE_EXPLODE, '=>', $introRaw));
		foreach ($baseParse as $row){
			$parseRow = self::pregRaw(self::TYPE_EXPLODE, ':', $row);
			$tableName = $parseRow[0];
			$fields = array_filter(self::pregRaw(self::TYPE_EXPLODE, '->', $parseRow[1]));
			//下划线命名法转驼峰命名法
			$model = self::UnderlineToCamelCase($tableName);
			$rst[] = [
				'name' => $tableName,
				'fields' => $fields,
				'model' => $model
			];
		}
		return $rst;
	}

	/**
	 * 根据正则匹配对应的结果
	 * @param string $parseType
	 * @param $pattern
	 * @param $raw
	 * @return mixed
	 */
	private static function pregRaw($parseType = self::TYPE_PREG, $pattern, $raw)
	{
		$parseRst = [];

		if ($parseType == self::TYPE_PREG){
			preg_match_all($pattern, $raw, $parseRst);
			array_shift($parseRst);
		}elseif($parseType == self::TYPE_EXPLODE){
			$raw = self::clearTabs($raw);
			$parseRst = explode($pattern, $raw);
		}

		return $parseRst;
	}

	/**
	 * 清除制表符
	 * @param $raw
	 * @return mixed
	 */
	private static function clearTabs($raw)
	{
		$raw = preg_replace('/[\t\r\n\s]/', '', $raw);
		return $raw;
	}

	/**
	 * 下划线命名法转驼峰命名法
	 * @param $str
	 * @return mixed
	 */
	private static function UnderlineToCamelCase($str)
	{
		// 去除空格(单词首字母大写(将下划线替换为空格))
		return preg_replace('# #', '', ucwords(str_replace('_', ' ', $str)));
	}

}
