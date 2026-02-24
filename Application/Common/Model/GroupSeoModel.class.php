<?php
namespace Common\Model;
use Common\Model\BaseModel;
/**
 * ModelName
 */
class GroupSeoModel extends BaseModel{
    // // 自动验证
    // protected $_validate=array(
    //     array('username','require','用户名必须',0,'',3), // 验证字段必填
    // );

    // 自动完成
    protected $_auto=array(
        array('addtime','time',1,'function'), // 对date字段在新增的时候写入当前时间戳
    );
    
	/**
	 * 获取全部菜单
	 * @param  string $type tree获取树形结构 level获取层级结构
	 * @return array       	结构数据
	 */
	public function getTreeData(){
		// 判断是否需要排序
		$data=$this->select();
		// 获取树形或者结构数据
		$data=\Org\Nx\Data::tree($data,'product','id','pid');
		// p($data);die;
		return $data;
	}

    /**
     *  获取产品信息
     */ 
    public function getData($id=''){
        if ($id) {
            return $this->where(['id'=>$id])->find();
        }else{
            return $this->select();
        }
        
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

}