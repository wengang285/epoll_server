#include "network.h"

#include <iostream>
#include <cstdlib>
#include <string>
#include <vector>
#include <fstream>  
#include <sys/epoll.h>




using namespace std;

int get_listen_fd();
void do_epoll(int listenfd);
void mylog(const char *s);

int main(int argc, const char *argv[])
{	
	mylog("log begin");
    if(signal(SIGPIPE, SIG_IGN) == SIG_ERR){
		//mylog(msg.c_str());
		mylog("signal SIGPIPE error");
		
	}
    //mylog("signal");
    
    int listenfd = get_listen_fd();

    do_epoll(listenfd);

    close(listenfd);
    return 0;
}



int get_listen_fd()
{
    //创建socket
    int listenfd = socket(PF_INET, SOCK_STREAM, 0);
    if(listenfd == -1)
        mylog("socket");


    //设置端口复用
    int on = 1;
    if (setsockopt(listenfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on)) < 0)
        mylog("setsockopt");

    struct sockaddr_in servaddr;
    servaddr.sin_family = AF_INET;
    servaddr.sin_port = htons(9001);
    servaddr.sin_addr.s_addr = htonl(INADDR_ANY);
    //bind端口
    if(bind(listenfd, (struct sockaddr*)&servaddr, sizeof servaddr) < 0)
        mylog("bind"); 

    //listen端口
    if(listen(listenfd, SOMAXCONN) < 0)
        mylog("listen");

    return listenfd;
}


//分割字符串
/*
void split(char *src, const char *separator, char **dest, int *num)
{
    char *pNext;
    int count = 0;
    
    if (src == NULL || strlen(src) == 0) return;
    if (separator == NULL || strlen(separator) == 0) return; 

    pNext = strtok(src,separator);
    
    while(pNext != NULL)
    {
        *dest++ = pNext;
        ++count;
        pNext = strtok(NULL,separator);
    }

    *num = count;
}
*/

void split(const string& src, const string& separator, vector<string>& dest)
{
    string str = src;
    string substring;
    string::size_type start = 0, index;

    do
    {
        index = str.find_first_of(separator,start);
        if (index != string::npos)
        {    
            substring = str.substr(start,index-start);
            dest.push_back(substring);
            start = str.find_first_not_of(separator,index);
            if (start == string::npos) return;
        }
    }while(index != string::npos);
    
    //the last token
    substring = str.substr(start);
    dest.push_back(substring);
}

//替换
string&   replace_all(string&   str,const   string&   old_value,const   string&   new_value)     
{     
    while(true)   {     
        string::size_type   pos(0);     
        if(   (pos=str.find(old_value))!=string::npos   )     
            str.replace(pos,old_value.length(),new_value);     
        else   break;     
    }     
    return   str;     
} 


void mylog(const char *s)
{
	
	time_t t;
	tm *tp;
	
	t = time(NULL);
	
	tp=localtime(&t);
	
	ofstream foi("/data/home/gavinwen/ckv_update/master.log",ios::app);
	
	//foi.open("/web/gavinwen/workspace/shell/cache_update/master.log",ios::in|ios::out,0);
	
	foi<<"["<<tp->tm_year+1900<<"-"<<tp->tm_mon+1<<"-"<<tp->tm_mday<<" "<<tp->tm_hour<<":"<<tp->tm_min<<":"<<tp->tm_sec<<"]"<<s<<"\n";
	
	foi.close();
	
}



void do_epoll(int listenfd)
{
    //创建epoll
    int epollfd = epoll_create(2048);
    if(epollfd == -1)
        mylog("epoll_create");
    //添加listenfd
    struct epoll_event ev;
    ev.data.fd = listenfd;
    ev.events = EPOLLIN;
    if(epoll_ctl(epollfd, EPOLL_CTL_ADD, listenfd, &ev) == -1)
        mylog("epoll_ctl");
    //创建数组
    struct epoll_event events[2048];
    int nready;

    while(1)
    {
        //wait
        nready = epoll_wait(epollfd, events, 2048, -1);
        if(nready == -1)
        {
            if(errno == EINTR)
                continue;
            mylog("epoll_wait");
        }
        //遍历events数组

        int i;
        for(i = 0; i < nready; ++i)
        {
            //如果是listenfd
            if(events[i].data.fd == listenfd)
            {
                int peerfd = accept(listenfd, NULL, NULL);
                if(peerfd == -1)
                    mylog("accept");
                //加入epoll
                struct epoll_event ev;
                ev.data.fd = peerfd;
                ev.events = EPOLLIN;
                if(epoll_ctl(epollfd, EPOLL_CTL_ADD, peerfd, &ev) == -1)
                    mylog("epoll_ctl");

            }else   //如果是普通fd
            {
                int peerfd = events[i].data.fd;
                char recvbuf[1024] = {0};
                int ret = readline(peerfd, recvbuf, 1024);
                if(ret == -1)
                    mylog("readline");
                else if(ret == 0)
                {
                    printf("client close\n");
                    //从epoll中删除
                    struct epoll_event ev;
                    ev.data.fd = peerfd;
                    if(epoll_ctl(epollfd, EPOLL_CTL_DEL, peerfd, &ev) == -1)
                        mylog("epoll_ctl");
                    close(peerfd);
                    continue;
                }

				
				string delimter = "&";
				
				//int len;
				vector<string> dest;
				
				vector<string> value;
				
				string src(recvbuf);
				
				
				
				//去掉\n
				//cout<<replace_all(src,"\n","")<<endl;
				
				
				src=replace_all(src,"\n","");
				
				mylog(src.c_str());
				
				
				//cout<<src<<endl;
				
				
				
				vector<string>::iterator p, q;
				
				split(src,delimter,dest);
				
				
				string iId="";
				string iActId="";
				string filePath="";
				string service="";
				string channel="";
				
				
				for(p=dest.begin();p!=dest.end();++p)
				{
					cout << *p << endl;
					value.clear();
					split(*p,"=",value);
					for (q=value.begin();q!=value.end();++q){
						//cout << *q << endl;
						if(*q=="iId"){
							iId=(string)*(q+1);
						}
						else if(*q=="iActId"){
							iActId = (string)*(q+1);
						}
						else if(*q=="filePath"){
							
							filePath = (string)*(q+1);
							//cout << "filePath="<<filePath<< endl;
						}
						else if(*q=="service"){
							service = (string)*(q+1);
						}
						else if(*q=="channel"){
							channel = (string)*(q+1);
						}
						
					}
						//cout << "/t" << *q << endl;
				}
				
				
				//检查参数
				if(iId.empty() || iActId.empty() || filePath.empty() || service.empty()){
					//string errBuf = "result=-1&info=param error\n";
					char errBuf[] = "result=-1&info=param error\n";
					writen(peerfd, errBuf, strlen(errBuf));
				}
				else{
					//string successBuf = "result=0&info=ok\n";
					char successBuf[] = "result=0&info=ok\n";
					
					//sprintf(buffer,"sh /data/ckv_update/ckv_update.sh %s %s %s %s &",filePath.c_str(),service.c_str(),iActId.c_str(),iId.c_str());
					
					system("chmod 777 /data/home/gavinwen/ckv_update/ckv_update.sh");
					
					string shell="/data/home/gavinwen/ckv_update/ckv_update.sh "+filePath+" "+service+" "+iActId+" "+iId+" "+channel+" &";
					mylog(shell.c_str());
					//shell=shell+filePath.c_str()+" "+service.c_str()+" "+iActId.c_str()+" "+iId.c_str()+" &";
					//cout<<shell<<endl;
					//shell=shell+filePath.c_str()+" ";
					system(shell.c_str());
					writen(peerfd, successBuf, strlen(successBuf));
				}
				
				//cout<<"iId="<<iId<<"iActId="<<iActId<<"filePath="<<filePath<<"service="<<service<<endl;
            }

        }



    }

    //关闭epoll句柄
    close(epollfd);
}
