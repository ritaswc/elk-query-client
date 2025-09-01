# elk-query-client

Query Elk Log Content



## Usage:

```php
    $url    = 'http://172.16.121.32:9200';
    $client = new QueryClient($url, 'tiger_logs');
    $list   = [];
    $client->query(['keyword for search'], function (array $item) use (&$list) {
        $logContent = trim($item['fields']['event.original'][0] ?? '');
        $logContent = mb_substr($logContent, mb_strpos($logContent, '{'));
        $arr        = json_decode($logContent, true);
        if (is_array($arr)) {
            $manualType = $arr['manualType'] ?? '';
            if (strlen($manualType)) {
                $list[$manualType] = $manualType;
            }
        }
    }, [strtotime('2025-01-01 00:00:00'), time()]);
    dd($list);
```

QueryClient will query all of the target data and auto complete paginator

工具将会查询所有的目标数据，并自动完成分页