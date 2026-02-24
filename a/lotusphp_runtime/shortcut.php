<?php
namespace Lotus;
function C($className)
{
	return LtObjectUtil::singleton($className);
}
