<?php
namespace Home\Controller;
use Think\Controller;

class TicketController extends Controller {

    /**
     * 我的工单列表
     */
    public function index() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $userId = session('users.id');
        $list = D('Ticket')->getByUser($userId);
        $this->assign('list', $list ?: array());
        $this->display();
    }

    /**
     * 提交工单
     */
    public function create() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        if (IS_POST) {
            $data = array(
                'user_id'  => session('users.id'),
                'title'    => I('post.title', '', 'htmlspecialchars'),
                'type'     => I('post.type', 0, 'intval'),
                'priority' => I('post.priority', 0, 'intval'),
                'content'  => I('post.content', '', 'htmlspecialchars'),
                'status'   => 0,
            );
            if (empty($data['title']) || empty($data['content'])) {
                if (IS_AJAX) {
                    $this->ajaxReturn(array('status' => 0, 'msg' => '标题和内容不能为空'));
                }
                $this->error('标题和内容不能为空');
            }
            $res = D('Ticket')->addData($data);
            if ($res) {
                if (IS_AJAX) {
                    $this->ajaxReturn(array('status' => 1, 'msg' => '工单提交成功', 'url' => U('Home/Ticket/index')));
                }
                $this->success('工单提交成功', U('Home/Ticket/index'));
            } else {
                if (IS_AJAX) {
                    $this->ajaxReturn(array('status' => 0, 'msg' => '提交失败，请重试'));
                }
                $this->error('提交失败，请重试');
            }
        } else {
            $this->display();
        }
    }
    /**
     * 工单详情
     */
    public function detail() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $id = I('get.id', 0, 'intval');
        $userId = session('users.id');
        if (!$id) {
            $this->error('参数错误');
        }
        $ticket = M('ticket')->where(array('id' => $id, 'user_id' => $userId))->find();
        if (!$ticket) {
            $this->error('工单不存在');
        }
        $replies = M('ticket_reply')->alias('r')
            ->field('r.*, u.username')
            ->join('LEFT JOIN yg_user u ON u.id = r.user_id')
            ->where(array('r.ticket_id' => $id))
            ->order('r.id asc')
            ->select();

        $this->assign('ticket', $ticket);
        $this->assign('replies', $replies ?: array());
        $this->display();
    }

    /**
     * 用户回复工单
     */
    public function reply() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        if (!IS_POST) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '非法请求'));
            }
            $this->error('非法请求');
        }
        $ticketId = I('post.ticket_id', 0, 'intval');
        $content  = I('post.content', '', 'htmlspecialchars');
        $userId   = session('users.id');
        if (!$ticketId || empty($content)) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误'));
            }
            $this->error('参数错误');
        }
        // 验证工单属于当前用户
        $ticket = M('ticket')->where(array('id' => $ticketId, 'user_id' => $userId))->find();
        if (!$ticket) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '工单不存在'));
            }
            $this->error('工单不存在');
        }
        if ($ticket['status'] == 3) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '工单已关闭，无法回复'));
            }
            $this->error('工单已关闭，无法回复');
        }
        $replyData = array(
            'ticket_id' => $ticketId,
            'user_id'   => $userId,
            'content'   => $content,
            'is_admin'  => 0,
        );
        $res = D('TicketReply')->addData($replyData);
        if ($res) {
            M('ticket')->where(array('id' => $ticketId))->save(array(
                'status'     => 1,
                'updated_at' => time(),
            ));
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '回复成功', 'url' => U('Home/Ticket/detail', array('id' => $ticketId))));
            }
            $this->success('回复成功', U('Home/Ticket/detail', array('id' => $ticketId)));
        } else {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '回复失败'));
            }
            $this->error('回复失败');
        }
    }

    /**
     * 关闭自己的工单
     */
    public function close() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        $id = I('get.id', 0, 'intval');
        $userId = session('users.id');
        $ticket = M('ticket')->where(array('id' => $id, 'user_id' => $userId))->find();
        if (!$ticket) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '工单不存在'));
            }
            $this->error('工单不存在');
        }
        $res = M('ticket')->where(array('id' => $id))->save(array(
            'status'     => 3,
            'closed_at'  => time(),
            'updated_at' => time(),
        ));
        if ($res !== false) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '工单已关闭', 'url' => U('Home/Ticket/index')));
            }
            $this->success('工单已关闭', U('Home/Ticket/index'));
        } else {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '操作失败'));
            }
            $this->error('操作失败');
        }
    }

    /**
     * 评价工单
     */
    public function rate() {
        if (!check_user_login()) {
            $this->error('请先登录', '/login');
        }
        if (!IS_POST) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '非法请求'));
            }
            $this->error('非法请求');
        }
        $id     = I('post.ticket_id', 0, 'intval');
        $rating = I('post.rating', 0, 'intval');
        $userId = session('users.id');
        if (!$id || $rating < 1 || $rating > 5) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '参数错误'));
            }
            $this->error('参数错误');
        }
        $ticket = M('ticket')->where(array('id' => $id, 'user_id' => $userId))->find();
        if (!$ticket || $ticket['status'] != 3) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '只能评价已关闭的工单'));
            }
            $this->error('只能评价已关闭的工单');
        }
        if (!empty($ticket['rating'])) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '您已评价过该工单'));
            }
            $this->error('您已评价过该工单');
        }
        $res = M('ticket')->where(array('id' => $id))->save(array(
            'rating'     => $rating,
            'updated_at' => time(),
        ));
        if ($res !== false) {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 1, 'msg' => '评价成功', 'url' => U('Home/Ticket/detail', array('id' => $id))));
            }
            $this->success('评价成功', U('Home/Ticket/detail', array('id' => $id)));
        } else {
            if (IS_AJAX) {
                $this->ajaxReturn(array('status' => 0, 'msg' => '评价失败'));
            }
            $this->error('评价失败');
        }
    }
}
