<?php
namespace Common\Model;
use Think\Model;

class EmailTemplateDbModel extends Model {
    protected $tableName = 'email_template';

    public function getByName($name) {
        return $this->where(array('name' => $name, 'is_active' => 1))->find();
    }

    public function getAllTemplates() {
        return $this->order('id asc')->select();
    }

    public function renderTemplate($name, $variables = array()) {
        $tpl = $this->getByName($name);
        if (!$tpl) return false;
        $content = $tpl['content'];
        $subject = $tpl['subject'];
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }
        return array('subject' => $subject, 'content' => $content);
    }
}
