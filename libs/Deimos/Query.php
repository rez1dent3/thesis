<?php

namespace Deimos;

class Query
{

    /**
     * @var QueryParser
     */
    private $parser;

    /**
     * @var array SemanticParser
     */
    private $semanticParser;

    /**
     * Query constructor.
     * @param $sql
     */
    public function __construct($sql)
    {
        $this->parser = new QueryParser($sql);
    }

    private function getValue($stdObj)
    {
        $res = array();
        if ($stdObj->sub_tree) {
            foreach ($stdObj->sub_tree as $sub) {
                $res[] = $this->getValue($sub)[0];
            }
        }
        else if (isset($stdObj->no_quotes) &&
            isset($stdObj->no_quotes->parts) &&
            isset($stdObj->no_quotes->parts->{0})
        ) {
            $res[] = $stdObj->no_quotes->parts->{0};
        }
        else {
            $res[] = $stdObj->base_expr;
        }
        return $res;
    }

    public function execute()
    {

        $params = array('from', 'select');

        /**
         * @var $from \stdClass
         * @var $where \stdClass
         * @var $select \stdClass
         */
        foreach ($params as $param) {
            $$param = $this->parser->{$param}();
            if (!$$param) {
                throw new \Exception($param);
            }
        }

        $semanticArray = array();

        foreach ($from as $table) {

            $filePath = $table->table;

            if (isset($table->no_quotes) && isset($table->no_quotes->parts) && isset($table->no_quotes->parts->{'0'})) {
                $filePath = $table->no_quotes->parts->{'0'};
            }

            $alias = $filePath;
            $pathInfo = pathinfo($filePath);

            if (isset($pathInfo['filename'])) {
                $alias = $pathInfo['filename'];
            }

            if ($table->alias && $table->alias->name) {
                $alias = $table->alias->name;
            }

            $parser = new Parser(file_get_contents($filePath));

            $this->semanticParser[$alias] = new SemanticParser($parser);
            $this->semanticParser[$alias]->execute();

            $opr = 'OR';

            $where = $this->parser->where();
            for ($i = 0; $i < count((array)$where);) {

                $parts = explode('.', $this->getValue($where->{$i++})[0]);

                $operator = $this->getValue($where->{$i++});
                $const = $this->getValue($where->{$i++});

                if ($operator[0] == 'BETWEEN') {
                    $operator[0] = '=';
                    $const = range($const[0], $this->getValue($where->{++$i})[0]);
                    $i++;
                }

                $newData = array_filter(
                    (array)$this->semanticParser[$alias]->getSemanticData()->{current($parts)},
                    function ($value) use ($parts, $operator, $const) {

                        $ind = 1;
                        while (count($parts) != $ind) {
                            $part = $parts[$ind];
                            $value = (array)$value;
                            if (!isset($value[$part])) {
                                return false;
                            }
                            $value = $value[$part];
                            $ind++;
                        }

                        $ind = 0;
                        foreach ($operator as $opr) {

                            if (is_array($value) || is_object($value)) {
                                break;
                            }

                            if ($opr == '<' && $value < min($const)) {
                                $ind++;
                            }

                            if ($opr == '>' && $value > max($const)) {
                                $ind++;
                            }

                            if ($opr == '=' && in_array($value, $const)) {
                                $ind++;
                            }

                            if ($opr == 'IS') {
                                foreach ($const as $c) {
                                    if ($c === $value) {
                                        $ind++;
                                    }
                                }
                            }

                        }

                        return $ind;

                    }

                );

                if ($opr == 'AND') {
                    $semanticArray = array_uintersect($semanticArray, $newData, function ($a, $b) {
                        return strcmp(spl_object_hash($a), spl_object_hash($b));
                    });
                }
                else if ($opr == 'OR') {
                    $semanticArray = array_merge($semanticArray, $newData);
                }

                if (isset($where->{$i})) {
                    $opr = $this->getValue($where->{$i++})[0];
                }

            }

            if (count((array)$where) == 0) {
                $semanticArray = $this->semanticParser[$alias]->getSemanticData();
            }

            foreach ($this->parser->orderBy() as $o) {
                // todo
            }

            foreach ($select as $s) {
                // todo
            }

            break; // first file
        }

        return $semanticArray;

    }

}