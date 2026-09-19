export interface WalletData {
    id: number;
    user_id: number;
    // 现金资产维度
    balance: number;                // 可用余额 (元)
    frozen_balance: number;         // 冻结金额 (元)
    // 虚拟金币维度
    coins: number;                  // 可用虚拟金币
    frozen_coins: number;           // 冻结虚拟金币
    // 累计统计指标
    total_recharge: number;         // 累计充值总额 (元)
    total_withdrawn: number;        // 累计提现总额 (元)
    total_spent: number;            // 累计消费总额 (元)
    total_earned_coins: number;     // 历史累计获得金币总额
    // 状态与管控
    status: number;                 // 1: 正常, 2: 冻结, 3: 禁用
    status_label: string;
    version: number;
}

export interface WalletTransactionItem {
    id: number;
    trx_no: string;                 // 全局唯一流水单号
    currency_type: 'balance' | 'coins' | 'frozen_balance'; // 资产类型
    type: string;                   // 业务类型标识 (check_in, recharge 等)
    type_label: string;             // 业务类型中文描述
    payment_method?: string | null; // 支付/充值渠道标识 (wechat, alipay 等)
    payment_method_label?: string | null; // 充值渠道中文名称 (微信, 支付宝 等)
    direction: 1 | -1;              // 资金流向: 1-收入(+), -1-支出(-)
    amount: number;                 // 变动数值
    balance_before: number;         // 变动前资产快照
    balance_after: number;          // 变动后资产快照
    description: string;            // 账单展示文案
    created_at: string;             // 发生时间
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
