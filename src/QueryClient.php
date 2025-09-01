<?php

namespace Ritaswc\ElkQueryClient;

class QueryClient
{
    protected string $urlPrefix   = '';
    protected string $dataView    = '';
    protected int    $pageSize    = 500;
    protected array  $searchAfter = [];
    protected        $logger      = null;
    const DEFAULT_PARAMS = '{"sort":[{"@timestamp":{"order":"desc","format":"strict_date_optional_time","unmapped_type":"boolean"}},{"_doc":{"order":"desc","unmapped_type":"boolean"}}],"track_total_hits":false,"fields":[{"field":"*","include_unmapped":"true"},{"field":"@timestamp","format":"strict_date_optional_time"}],"size":500,"version":true,"script_fields":{},"stored_fields":["*"],"runtime_mappings":{},"_source":false,"query":{"bool":{"must":[],"filter":[{"bool":{"filter":[]}},{"range":{"@timestamp":{"format":"strict_date_optional_time","gte":"2025-08-17T02:01:35.343Z","lte":"2025-09-01T02:01:35.343Z"}}}],"should":[],"must_not":[]}},"highlight":{"pre_tags":["@kibana-highlighted-field@"],"post_tags":["@/kibana-highlighted-field@"],"fields":{"*":{}},"fragment_size":2147483647}}';

    protected int $timeout = 600;

    protected function setTimeout(int $timeout = 600): QueryClient
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setPageSize(int $size): QueryClient
    {
        $this->pageSize = $size;
        return $this;
    }

    protected function getTimeout(): int
    {
        return $this->timeout;
    }

    public function __construct(string $urlPrefix, string $dataView)
    {
        $this->urlPrefix = $urlPrefix;
        $this->dataView  = $dataView;
    }

    protected function timeRangeArray(array $timeRange): object
    {
        $stdClass                      = new \stdClass();
        $stdClass->range               = new \stdClass();
        $key                           = '@timestamp';
        $stdClass->range->$key         = new \stdClass();
        $stdClass->range->$key->format = 'strict_date_optional_time';
        $stdClass->range->$key->gte    = (new \DateTime())->setTimestamp($timeRange[0])->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $stdClass->range->$key->lte    = (new \DateTime())->setTimestamp($timeRange[1])->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        return clone $stdClass;
    }

    protected function keywordArray(array $keywords): object
    {
        $array = [];
        foreach ($keywords as $keyword) {
            $item                       = new \stdClass();
            $item->multi_match          = new \stdClass();
            $item->multi_match->type    = 'phrase';
            $item->multi_match->query   = $keyword;
            $item->multi_match->lenient = true;
            $array[]                    = $item;
        }
        $obj               = new \stdClass();
        $obj->bool         = new \stdClass();
        $obj->bool->filter = $array;
        return $obj;
    }

    protected function setKeywords(object $param, array $keywords): object
    {
        if (count($keywords)) {
            // 确保 query->bool->filter 存在且是数组
            if (!isset($param->query->bool->filter)) {
                $param->query->bool->filter = [];
            }
            $filters        = &$param->query->bool->filter;
            $keywordFilters = $this->keywordArray($keywords);
            $keywordExists  = false;
            foreach ($filters as $k => $filter) {
                if (isset($filter->bool->filter)) {
                    // 替换现有的时间范围过滤器
                    $filters[$k]   = $keywordFilters;
                    $keywordExists = true;
                    break;
                }
            }
            if (!$keywordExists) {
                $filters[] = $keywordFilters;
            }
        }
        return $param;
    }

    protected function setLogger($logger): QueryClient
    {
        $this->logger = $logger;
        return $this;
    }

    protected function setTimeRange(object $param, array $timeRange): object
    {
        if (count($timeRange)) {
            // 确保 query->bool->filter 存在且是数组
            if (!isset($param->query->bool->filter)) {
                $param->query->bool->filter = [];
            }

            $filters         = &$param->query->bool->filter;
            $timeRangeFilter = $this->timeRangeArray($timeRange);

            // 检查是否已存在时间范围过滤器
            $timeRangeExists = false;
            foreach ($filters as $k => $filter) {
                if (isset($filter->range->{'@timestamp'})) {
                    // 替换现有的时间范围过滤器
                    $filters[$k]     = $timeRangeFilter;
                    $timeRangeExists = true;
                    break;
                }
            }

            // 如果不存在，添加新的时间范围过滤器
            if (!$timeRangeExists) {
                $filters[] = $timeRangeFilter;
            }
        }

        return $param;
    }

    public function query(array $keywords, $callback, array $timeRange = [])
    {
        $totalCount = 0;
        $param      = json_decode(self::DEFAULT_PARAMS);
        if (count($timeRange)) {
            $this->setTimeRange($param, $timeRange);
        }
        if (count($keywords)) {
            $this->setKeywords($param, $keywords);
        }
        $param->size = $this->pageSize;
        $url         = rtrim($this->urlPrefix, '/') . '/' . trim($this->dataView, ' /') . '/_search';
        while (true) {
            if (count($this->searchAfter)) {
                $param->search_after = $this->searchAfter;
            }
            $res  = json_decode($this->request($url, $param)['body'] ?? '', true);
            $hits = $res['hits']['hits'] ?? [];
            foreach ($hits as $hit) {
                if (is_array($hit) && count($hit)) {
                    $this->searchAfter = $hit['sort'];
                    $callback($hit);
                    $totalCount++;
                }
            }
            if (count($hits) < $this->pageSize) {
                break;
            }
        }
        return $totalCount;
    }

    protected function request(string $url, object $param): array
    {
        $ch       = curl_init($url);
        $jsonData = json_encode($param);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FAILONERROR    => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData),
                'User-Agent: RitaswcElkQueryClient/1.0.0',
            ]
        ]);
        $body      = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);
        if ($logger) {
            $logger->info("Elasticsearch result：" . json_encode($requestArr, JSON_UNESCAPED_UNICODE) . "\n" . $body);
        }
        return compact('body', 'httpCode', 'curlError', 'curlErrno');
    }
}
