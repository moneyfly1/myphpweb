<?php
namespace Common\Model;
use Common\Model\BaseModel;
/**
 * ModelName
 */
class DingyueModel extends BaseModel{
    // // 自动验证
    // protected $_validate=array(
    //     array('username','require','用户名必须',0,'',3), // 验证字段必填
    // );

    // 自动完成
    // protected $_auto=array(
    //     array('addtime','time',1,'function'), // 对date字段在新增的时候写入当前时间戳
    // );


    /**
     *  获取产品信息
     *  map ['id'=>1]
     */ 
    public function getData($map=false){
        if (!empty($map)) {
            return $this->where($map)->find();
        }else{
            return $this->where(['status'=>1])->select();
        }
    }

    /**
     *  获取多产品信息
     *  map ['id'=>1]
     */ 
    public function getAllData($map=false){
        return $this->where($map)->select();
    }

    /**
     *  多条件判断获取产品信息
     *  qq\mobileurl\
     */ 
    public function getOrData($search){
        $where['qq'] = $search;
        $where['mobileurl'] = $search;
        $where['clashurl'] = $search;
        $where['mobileshorturl'] = $search;
        $where['clashshorturl'] = $search;
        $where['_logic'] = 'or';
        $map['_complex'] = $where;
        return $this->where($map)->find();
        
    }

    /**
     * 添加产品
     */
    public function addData($data){
        // 对data数据进行验证
        if(!$data=$this->create($data)){
            // 验证不通过返回错误
            return false;
        }else{
            // 验证通过
            $result=$this->add($data);
            return $result;
        }
    }

    /**
     * 修改产品
     */
    public function editData($map,$data){
        // 对data数据进行验证
        if(!$data=$this->create($data)){
            // 验证不通过返回错误
            return false;
        }else{
            // 验证通过
            $result=$this
                ->where(array($map))
                ->save($data);
            return $result;
        }
    }

    /**
     * 删除数据
     * @param   array   $map    where语句数组形式
     * @return  boolean         操作是否成功
     */
    public function deleteData($map){
        $result=$this->where($map)->delete();
        return $result;
    }

}