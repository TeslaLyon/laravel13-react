import React from 'react';
import BaseDetailShow from '@/components/common/BaseDetailShow';

export default function ChannelShow(props: any) {
    return (
        <BaseDetailShow
            moduleType="channel" // 不会渲染“修正资料”按钮
            entity={props.channel}
            {...props}
        />
    );
}