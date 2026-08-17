import React from 'react';
import BaseDetailShow, { EntityData } from '@/components/common/BaseDetailShow';

interface CategoryShowPageProps {
    entity: EntityData;
    currentTab?: string;
    initisFollowed?: boolean;
    latestVideos?: any[];
    latestPhotos?: any[];
    paginatedVideos?: any;
    paginatedPhotos?: any;
}

export default function CategoryShowPage({
    entity,
    currentTab = 'home',
    initisFollowed = false,
    latestVideos,
    latestPhotos,
    paginatedVideos,
    paginatedPhotos,
}: CategoryShowPageProps) {

    return (
        <BaseDetailShow
            moduleType="category"
            entity={entity}
            currentTab={currentTab}
            initisFollowed={initisFollowed}
            latestVideos={latestVideos}
            latestPhotos={latestPhotos}
            paginatedVideos={paginatedVideos}
            paginatedPhotos={paginatedPhotos}
        />
    );
}
