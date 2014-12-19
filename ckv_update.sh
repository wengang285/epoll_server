#!/bin/sh
#获取需要更新的文件路径、业务名称、任务id
#拆分文件->单个进程读取文件->更新ckv->更新任务完成标志位->通知调用端
#

#获取shell脚本当前目录

#参数小于三个，提出

export LD_LIBRARY_PATH=/usr/local/ieod-web/lib:/usr/local/lib:/usr/local/oss_dev/lib:/usr/local/ice/lib:/usr/local/mysql/lib/mysql/:$LD_LIBRARY_PATH

filepath=$(cd "$(dirname "$0")"; pwd)

if [ $# -lt 5 ]; then
	echo "param num is less then 4">>$filepath"/master.log"
	mysql -uoss -poss_da -h10.206.30.110 -P3361 dbCFClientPopJensenZhang  -sBe "update tbUploadFile set iCKVStatus=-1,dtUpdateTime=now() where iId=$task_id;"
	exit
fi





data_path=$1


service=$2

activity_id=$3

task_id=$4

#新增渠道
channel=$5

#echo $data_path


#源文件不存在
if [ ! -f $data_path ]; then
	#记录日志
	echo $data_path" is not exist">>$filepath"/master.log"
	#更新数据表
	mysql -uoss -poss_da -h10.206.30.110 -P3361 dbCFClientPopJensenZhang  -sBe "update tbUploadFile set iCKVStatus=-1,dtUpdateTime=now() where iId=$task_id;"
	exit
fi

line_num=`wc -l $data_path|awk '{print $1}'`

per_file_num=$((line_num/100))


data_file_name=`basename $data_path`



#任务目录
task_dir_path=$filepath"/"$task_id

rm -rf $task_dir_path

if [ ! -x $task_dir_path ]; then
	mkdir $task_dir_path
fi

#清除所有文件
#rm -rf $task_dir_path"/*"

#cp $data_path $task_dir_path"/"


#new_data_file=$task_dir_path"/"$data_file_name

#如果小于10000，则不需要拆分
if [ $line_num -lt 10000 ]; then
	cp $data_path $task_dir_path"/"
else
	#拆分成100文件
	split -l $per_file_num $data_path $task_dir_path"/"$service"_"$task_id"_"
fi

#调用php多进程处理

echo "/usr/local/ieod-web/php/bin/php ${filepath}/main_fork.php ${task_dir_path} ${service} ${activity_id} ${task_id} ${channel} >/dev/null &">>$filepath"/master.log"


/usr/local/ieod-web/php/bin/php $filepath"/main_fork.php" $task_dir_path $service $activity_id $task_id $channel >/dev/null &


#循环，多进程处理

#for file in `ls $task_dir_path`
#do
#	tmp_file_path=$task_dir_path"/"$file
	
	
	#sh $filepath"/single_file_update.sh" $tmp_file_path $service $task_id >/dev/null &
	
	#echo "sh $filepath"/single_file_update.sh" $tmp_file_path $service $task_id >/dev/null &"
	
#done





#echo $new_data_file
















