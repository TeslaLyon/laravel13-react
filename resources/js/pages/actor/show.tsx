import React from 'react';
import BaseDetailShow from '@/components/common/BaseDetailShow';

export default function ActorShow(props: any) {
    return (
        <BaseDetailShow
            moduleType="actor"  // 指定模块为 actor，会自动开启“修正资料”
            entity={props.actor}
            {...props}
        />
    );
}