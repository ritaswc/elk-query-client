# elk-query-client

Query Elk Log Content

Usage:

```php
    $url    = 'http://172.16.121.32:9200';
    $params = '{"sort":[{"@timestamp":{"order":"desc","format":"strict_date_optional_time","unmapped_type":"boolean"}},{"_doc":{"order":"desc","unmapped_type":"boolean"}}],"track_total_hits":false,"fields":[{"field":"*","include_unmapped":"true"},{"field":"@timestamp","format":"strict_date_optional_time"}],"size":500,"version":true,"script_fields":{},"stored_fields":["*"],"runtime_mappings":{},"_source":false,"query":{"bool":{"must":[],"filter":[{"bool":{"filter":[{"multi_match":{"type":"phrase","query":"搜索内容","lenient":true}},{"multi_match":{"type":"phrase","query":"manualType","lenient":true}}]}},{"range":{"@timestamp":{"format":"strict_date_optional_time","gte":"2025-08-17T02:01:35.343Z","lte":"2025-09-01T02:01:35.343Z"}}}],"should":[],"must_not":[]}},"highlight":{"pre_tags":["@kibana-highlighted-field@"],"post_tags":["@/kibana-highlighted-field@"],"fields":{"*":{}},"fragment_size":2147483647}}';
    $client = new QueryClient($url, 'tiger_logs');
    $list   = [];
    $client->query('keyword for search', function (array $item) use (&$list) {
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
