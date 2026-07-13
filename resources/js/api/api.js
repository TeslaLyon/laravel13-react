// api.js 或放在组件顶部
export const fetchVideos = async () => {
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve([
        {
          id: '1',
          title: '如何使用 React + shadcn 打造现代化 UI 组件库 | 2024 完整教程',
          slug: '1',
          thumbnail: 'https://images.unsplash.com/photo-1633356122544-f134324a6cee?q=80&w=800&auto=format&fit=crop',
          duration: '14:20',
          channelName: '前端研究所',
          channelAvatar: 'https://github.com/shadcn.png',
          views: '1.2万次观看',
          uploadedAt: '2小时前',
        },
        {
          id: '2',
          title: '深入理解 Tailwind CSS：从入门到精通的最佳实践',
          slug: '2',
          thumbnail: 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=800&auto=format&fit=crop',
          duration: '08:45',
          channelName: 'Code Master',
          channelAvatar: '', // 测试空头像使用 Fallback
          views: '8000次观看',
          uploadedAt: '1天前',
        },
        // 你可以复制上面对象多生成几个模拟数据填满屏幕
        { id: '3', title: 'Vite 5.0 到底快在哪里？原理解析', slug: '3', thumbnail: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=800&auto=format&fit=crop', duration: '22:10', channelName: 'Web 前线', channelAvatar: 'https://github.com/shadcn.png', views: '5万次观看', uploadedAt: '3天前' },
        { id: '4', title: '10个提升工作效率的 VS Code 插件推荐', slug: '4', thumbnail: 'https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=800&auto=format&fit=crop', duration: '11:05', channelName: '开发日常', channelAvatar: 'https://github.com/shadcn.png', views: '3.1万次观看', uploadedAt: '1周前' },
      ]);
    }, 2000); // 模拟 2 秒的网络延迟
  });
};
