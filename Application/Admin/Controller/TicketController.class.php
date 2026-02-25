<?php
namespace Admin\Controller;
use Common\Controller\AdminBaseController;

class TicketController extends AdminBaseController {

    /**
     * 工单列表
     */
    public function index() {
        $status = I('get.status', '');
        $map = array();
        if ($status !== '') {
            $map['t.status'] = intval($status);
        }

        $model = M('ticket');
        // 状态统计
        $countAll   = $model->count();
        $countNew   = $model->where(array('status' => 0))->count();
        $countOpen  = $model->where(array('status' => 1))->count();
        $countReply = $model->where(array('status' => 2))->count();
        $countClose = $model->where(array('status' => 3))->count();

        // 分页查询（join user 获取用户名）
        $countQuery = $model->alias('t')
            ->join('LEFT JOIN __USER__ u ON u.id = t.user_id')
            ->where($map)
            ->count();

        $Page = new \Think\Page($countQuery, 15);
        $Page->setConfig('prev', '上一页');
        $Page->setConfig('next', '下一页');
        $Page->setConfig('theme', '%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END%');
        if ($status !== '') {
            $Page->parameter['status'] = $status;
        }

        $list = $model->alias('t')
            ->field('t.*, u.username')
            ->join('LEFT JOIN __USER__ u ON u.id = t.user_id')
            ->where($map)
            ->order('t.id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();

        $this->assign('list', $list);
        $this->assign('page', $Page->show());
        $this->assign('status', $status);
        $this->assign('countAll', $countAll);
        $this->assign('countNew', $countNew);
        $this->assign('countOpen', $countOpen);
        $this->assign('countReply', $countReply);
        $this->assign('countClose', $countClose);
        $this->display();
    }

    /**
     * 工单详情 & 管理员回复
     */
    public function detail() {
        $id = I('get.id', 0, 'intval');
        if (!$id) {
            $this->error('参数错误');
        }

        if (IS_POST) {
            $content = I('post.content', '', 'htmlspecialchars');
            if (empty($content)) {
                $this->error('回复内容不能为空');
            }
            $replyData = array(
                'ticket_id'  => $id,
                'user_id'    => $_SESSION['admin']['id'],
                'content'    => $content,
                'is_admin'   => 1,
            );
            $res = D('TicketReply')->addData($replyData);
            if ($res) {
                // 更新工单状态为已回复，更新时间
                M('ticket')->where(array('id' => $id))->save(array(
                    'status'     => 2,
                    'updated_at' => time(),
                ));
                $this->success('回复成功', U('Admin/Ticket/detail', array('id' => $id)));
            } else {
                $this->error('回复失败');
            }
            return;
        }

        $ticket = M('ticket')->alias('t')
            ->field('t.*, u.username')
            ->join('LEFT JOIN __USER__ u ON u.id = t.user_id')
            ->where(array('t.id' => $id))
            ->find();
        if (!$ticket) {
            $this->error('工单不存在');
        }
        $replies = M('ticket_reply')->alias('r')
            ->field('r.*, u.username')
            ->join('LEFT JOIN __USER__ u ON u.id = r.user_id')
            ->where(array('r.ticket_id' => $id))
            ->order('r.id asc')
            ->select();

        $this->assign('ticket', $ticket);
        $this->assign('replies', $replies ?: array());
        $this->display();
    }

    /**
     * 关闭工单
     */
    public function close() {
        $id = I('get.id', 0, 'intval');
        if (!$id) {
            $this->error('参数错误');
        }
        $res = M('ticket')->where(array('id' => $id))->save(array(
            'status'     => 3,
            'closed_at'  => time(),
            'updated_at' => time(),
        ));
        if ($res !== false) {
            $this->success('工单已关闭', U('Admin/Ticket/index'));
        } else {
            $this->error('操作失败');
        }
    }

    /**
     * 分配工单给管理员 (AJAX)
     */
    public function assign_admin() {
        $id = I('post.id', 0, 'intval');
        $adminId = I('post.admin_id', 0, 'intval');
        if (!$id || !$adminId) {
            $this->ajaxReturn(array('code' => 1, 'msg' => '参数错误'));
        }
        $res = M('ticket')->where(array('id' => $id))->save(array(
            'assigned_to' => $adminId,
            'status'      => 1,
            'updated_at'  => time(),
        ));
        if ($res !== false) {
            $this->ajaxReturn(array('code' => 0, 'msg' => '分配成功'));
        } else {
            $this->ajaxReturn(array('code' => 1, 'msg' => '分配失败'));
        }
    }

    /**
     * 删除工单
     */
    public function del() {
        $id = I('get.id', 0, 'intval');
        if (!$id) {
            $this->error('参数错误');
        }
        M('ticket_reply')->where(array('ticket_id' => $id))->delete();
        $res = M('ticket')->where(array('id' => $id))->delete();
        if ($res !== false) {
            $this->success('删除成功', U('Admin/Ticket/index'));
        } else {
            $this->error('删除失败');
        }
    }
}
