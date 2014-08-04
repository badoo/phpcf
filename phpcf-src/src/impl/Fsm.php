<?php
/**
 * Formatter's FSM
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 */
namespace Phpcf\Impl;

class Fsm
{
    public $rules = [];
    public $state = null;
    public $state_stack = [];
    public $old_state = null;
    public $prev_code = null;
    public $current_rules = [/* state, code_key, rules */];

    private $delayed_rule = null;

    private $debug_enabled = false;

    private $rule_state = null;

    /**
     * @var \Phpcf\ExecStat
     */
    private $Stat;

    /**
     * @param array $rules
     */
    public function __construct(array $rules)
    {
        if (!empty($rules)) {
            foreach ($rules as $key => $data) {
                if (is_array($data)) {
                    // $data: 'T_SOMETHING T_OTHER_SOMETHING' => rule_to_apply,
                    // we convert it to
                    // 'T_SOMETHING' => rule_to_apply,
                    // 'T_OTHER_SOMETHING' => rule_to_apply,
                    foreach ($data as $k => $v) {
                        $k_parts = explode(' ', $k);
                        if (count($k_parts) > 1) {
                            unset($data[$k]);
                            foreach ($k_parts as $kk) $data[$kk] = $v;
                        }
                    }
                }

                $parts = explode(' ', $key);
                if (empty($parts)) $parts = [$key];
                foreach ($parts as $key_sub) {
                    $this->rules[$key_sub] = $data;
                }
            }

            if (isset($rules[0])) {
                $this->rule_state = $this->state = $rules[0];
            }
        }
    }

    public function flush()
    {
        $this->state = $this->rule_state;
        $this->old_state = $this->prev_code = $this->delayed_rule = null;
        $this->state_stack = $this->current_rules = [];
    }

    /**
     * @param bool $flag
     */
    public function setIsDebug($flag)
    {
        $this->debug_enabled = (bool)$flag;
    }

    /**
     * @return array
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getStackPath()
    {
        $result = $this->state_stack;
        $result[] = $this->state;
        return implode(' / ', $result);
    }

    /**
     * Finalize FSM usage, applying delayed rules if needed
     */
    public function finalize()
    {
        $this->applyDelayed();
    }

    /**
     * Applying of delayed rule (delayed rules are rules that are executed after setting context of current token
     * but before starting to process next one)
     */
    private function applyDelayed()
    {
        if (isset($this->delayed_rule)) {
            if ($this->debug_enabled && $this->Stat) {
                $msg = "Found delayed rule: " . print_r($this->delayed_rule, true);
                $msg .= " in current state: {$this->state}";
                $this->Stat->addDebug($msg);
            }
            $this->state = $this->delayed_rule;
            $this->executeTransition();
            $this->delayed_rule = null;
        }
    }

    /**
     * Performs transition to ctx with $code
     * @param string $code
     */
    public function transit($code)
    {
        if ($code == 'T_WHITESPACE') {
            return;
        }

        $code_key = $code;

        $this->applyDelayed();

        if (isset($this->rules[$this->state])) {
            $i_rules = $this->rules[$this->state];
            if (!isset($i_rules[$code_key])) {
                $code_key = PHPCF_KEY_ALL;
            }

            if (!empty($i_rules[$code_key])) {
                $this->current_rules = [$this->state, $code];
                $this->old_state = $this->state;
                $this->state = $i_rules[$code_key];

                // you can specify delayed context switch as
                // array('NOW' => ..., 'NEXT' => ...)
                if (is_array($this->state) && isset($this->state['NOW'])) {
                    if (isset($this->state['NEXT'])) {
                        $this->delayed_rule = $this->state['NEXT'];
                    }
                    $this->state = $this->state['NOW'];
                }
                // replace  part of context stack with some instructions
                else if (is_array($this->state) && isset($this->state['REPLACE'])) {
                    $replace_instructions = $this->state['REPLACE'];
                    $this->state = $this->old_state;
                    $this->delayed_rule = $replace_instructions[0];
                    $this->applyDelayed();
                    $this->old_state = $this->state;
                    $this->state = $replace_instructions[1];
                }

                $this->executeTransition();
            }
        }

        $this->prev_code = $code;
    }

    private function executeTransition()
    {
        if (is_array($this->state)) {
            // context inside context
            $this->state = $this->state[0];
            $this->state_stack[] = $this->old_state;
        } elseif ($this->state < 0) {
            $i = -$this->state;
            while ($i > 0) {
                $this->state = array_pop($this->state_stack);
                $i--;
            }
        }
    }

    public function setExecStat(\PhpCf\ExecStat $Stat)
    {
        $this->Stat = $Stat;
    }
}
