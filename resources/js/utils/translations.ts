// utils/translations.ts

// 整合你原有的字典，统一管理
export const actorTranslations: Record<string, string> = {
    // 基础信息
    name: '姓名',
    aliases: '别名',
    official_website: '官方网站',
    feature_dancer: '特色舞者',
    date_of_birth: '出生日期',
    birthday: '出生日期',
    age: '年龄',
    gender: '性别',
    sexual_orientation: '性取向',
    same_sex_only: '仅同性',
    astrological_sign: '星座',
    astrology: '星座',
    profession: '职业',
    career_status: '职业状态',
    career_start: '职业生涯开始',
    career_start_year: '出道年份',
    career_end: '职业生涯结束',
    career_end_year: '退役年份',
    date_of_death: '逝世日期',
    deathday: '逝世日期',
    place_of_birth: '出生地',
    birthplace: '出生地',
    birthplace_code: '出生地代码',
    nationality: '国籍',

    // 外形数据
    ethnicity: '种族',
    boobs: '胸部',
    bust: '胸围',
    cup: '罩杯',
    cupsize: '罩杯',
    bra: '内衣',
    waist: '腰围',
    hip: '臀围',
    hips: '臀围',
    butt: '臀部',
    height: '身高',
    weight: '体重',
    measurements: '三围',
    fake_boobs: '假胸/隆胸',
    hair_color: '发色',
    hair_colour: '发色',
    eye_color: '瞳色',
    eye_colour: '瞳色',
    piercings: '穿孔',
    piercing_locations: '穿孔位置',
    tattoos: '纹身',
    tattoo_locations: '纹身位置',
    shoe_size: '鞋码',

    // 社交媒体 (通常保持原名，但如果需要翻译也可以加在这里)
    youtube: 'YouTube',
    facebook: 'Facebook',
    instagram: 'Instagram',
    x: 'X (Twitter)',
    twitter: 'Twitter',
    website: '官方网站',
    tiktok: 'TikTok',
    onlyfans: 'OnlyFans',
    pornhub: 'Pornhub',
    imdb: 'IMDb',
    iafd: 'IAFD',
    tmdb: 'TMDB',
    afdb: 'AFDB',
    linktree: 'Linktree',
    freeones: 'FreeOnes',
    babepedia: 'Babepedia',
    boobpedia: 'Boobpedia'
};

/**
 * 智能翻译 Key 的函数
 */
export const translateActorKey = (key: string): string => {
    // 1. 如果字典中存在，直接返回翻译
    if (actorTranslations[key.toLowerCase()]) {
        return actorTranslations[key.toLowerCase()];
    }

    // 2. 如果是用户自己填写的中文 Key，直接原样返回
    if (/[\u4e00-\u9fa5]/.test(key)) {
        return key;
    }

    // 3. 兜底策略：如果是未知的英文 Key，去除下划线并首字母大写
    return key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};
