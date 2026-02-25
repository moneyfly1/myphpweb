<?php
namespace Common\Model;
use Common\Model\BaseModel;
/**
 * 管理员模型 - 对应 yg_admin 表
 */
class AdminModel extends BaseModel
{
    // 自动验证
    protected $_validate = array(
        array('username', 'require', '用户名必须', 0, '', 3),
    );

    // 自动完成
    protected $_auto = array(
        array('password', 'secure_password_hash', 1, 'function'),
        array('register_time', 'time', 1, 'function'),
    );

    /**
     * 添加管理员
     */
    public function addData($data)
    {
        if (!$data = $this->create($data)) {
            return false;
        } else {
            $result = $this->add($data);
            return $result;
        }
    }

    /**
     * 修改管理员
     */
    public function editData($map, $data)
    {
        if (!$data = $this->create($data)) {
            return false;
        } else {
            $result = $this
                ->where(array($map))
                ->save($data);
            return $result;
        }
    }

    /**
     * 删除管理员
     */
    public function deleteData($map)
    {
        $result = $this->where($map)->delete();
        return $result;
    }

    /**
     * 获取分页管理员列表
     */
    public function getAdminPage($map = array(), $order = 'register_time desc', $limit = 15)
    {
        $count = $this->where($map)->count();
        $page = new_page($count, $limit);
        $list = $this->where($map)->order($order)
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        return array(
            'data' => $list,
            'page' => $page->show()
        );
    }
}
