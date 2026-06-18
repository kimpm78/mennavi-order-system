

<script setup lang="ts">
import {
  AlertTriangle,
  ChevronRight,
  Cookie,
  Database,
  FileLock2,
  Mail,
  ShieldCheck,
  UserRoundCheck,
} from 'lucide-vue-next'

const props = defineProps<{
  goTo?: (path: string) => void
}>()

const navigate = (path: string) => {
  if (props.goTo) {
    props.goTo(path)
    return
  }

  window.location.href = path
}

const privacySections = [
  {
    number: '1',
    title: '基本方針',
    paragraphs: [
      '麺ナビの運営者（以下「運営者」といいます。）は、利用者の個人情報を適切に保護することを重要な責務と考え、個人情報の保護に関する法令その他の関連規範を遵守します。',
      '運営者は、本プライバシーポリシーに基づき、個人情報を適正に取得、利用、管理します。',
    ],
    icon: ShieldCheck,
  },
  {
    number: '2',
    title: '取得する情報',
    paragraphs: [
      '運営者は、本サービスの提供にあたり、次の情報を取得する場合があります。',
    ],
    items: [
      '氏名、メールアドレス、電話番号、パスワードその他の会員登録情報',
      '配送先住所、受取人氏名、受取人電話番号その他の配送情報',
      '注文商品、注文金額、注文日時、注文状況、支払い状況その他の注文情報',
      'お問い合わせ内容およびお問い合わせへの対応履歴',
      'IPアドレス、ブラウザ情報、端末情報、アクセス日時、閲覧履歴その他の利用情報',
      'Cookieその他の識別子を通じて取得される情報',
    ],
    icon: Database,
  },
  {
    number: '3',
    title: '利用目的',
    paragraphs: [
      '取得した情報は、次の目的で利用します。',
    ],
    items: [
      '会員登録、本人確認、認証およびアカウント管理のため',
      '商品の注文受付、決済、配送および注文状況の案内のため',
      '店舗、配送事業者、決済事業者その他の関係事業者との連携のため',
      'お問い合わせへの回答およびサポート対応のため',
      '本サービスの維持、改善、不具合対応および新機能開発のため',
      '不正利用、規約違反、セキュリティ上の問題の防止および調査のため',
      '利用状況の分析、統計情報の作成およびサービス品質向上のため',
      '法令上の義務への対応および権利の保護のため',
    ],
    icon: UserRoundCheck,
  },
  {
    number: '4',
    title: '第三者提供',
    paragraphs: [
      '運営者は、次の場合を除き、利用者本人の同意なく個人情報を第三者へ提供しません。',
    ],
    items: [
      '法令に基づく場合',
      '人の生命、身体または財産の保護のために必要であり、本人の同意を得ることが困難な場合',
      '公衆衛生の向上または児童の健全な育成のために特に必要であり、本人の同意を得ることが困難な場合',
      '国または地方公共団体等が法令の定める事務を遂行することに協力する必要がある場合',
      '利用目的の達成に必要な範囲で、店舗、配送事業者、決済事業者その他の業務委託先へ提供する場合',
    ],
    icon: FileLock2,
  },
  {
    number: '5',
    title: '業務委託',
    paragraphs: [
      '運営者は、決済処理、配送、メール送信、システム運用、データ保管その他の業務を外部事業者へ委託する場合があります。',
      'この場合、委託先を適切に選定し、必要な契約および監督を行います。',
    ],
    icon: FileLock2,
  },
  {
    number: '6',
    title: '決済情報の取り扱い',
    paragraphs: [
      'クレジットカード情報その他の決済情報は、外部の決済事業者によって処理される場合があります。',
      '運営者は、決済事業者から決済結果、取引識別子その他注文管理に必要な情報を取得することがありますが、カード番号等の機密情報を直接保持しない場合があります。',
    ],
    icon: FileLock2,
  },
  {
    number: '7',
    title: 'Cookie等の利用',
    paragraphs: [
      '本サービスでは、ログイン状態の保持、利便性向上、利用状況の分析および不正利用防止のため、Cookieその他の類似技術を使用する場合があります。',
      '利用者はブラウザの設定によりCookieを無効にできますが、本サービスの一部機能が利用できなくなる場合があります。',
    ],
    icon: Cookie,
  },
  {
    number: '8',
    title: '安全管理措置',
    paragraphs: [
      '運営者は、個人情報への不正アクセス、漏えい、滅失、毀損、改ざん等を防止するため、アクセス制御、認証管理、通信の保護、ログ管理その他の必要かつ適切な安全管理措置を講じます。',
      '個人情報を取り扱う従業者および委託先に対して、必要な教育および監督を行います。',
    ],
    icon: ShieldCheck,
  },
  {
    number: '9',
    title: '保存期間',
    paragraphs: [
      '運営者は、利用目的の達成に必要な期間、法令上の保存義務がある期間、または紛争対応に必要な期間、個人情報を保存します。',
      '保存の必要がなくなった情報は、適切な方法で削除または匿名化します。',
    ],
    icon: Database,
  },
  {
    number: '10',
    title: '開示・訂正・削除等の請求',
    paragraphs: [
      '利用者は、法令の定めに基づき、自己の個人情報について、利用目的の通知、開示、訂正、追加、削除、利用停止、消去または第三者提供の停止を請求できます。',
      '請求を行う場合は、お問い合わせ窓口からご連絡ください。本人確認を行ったうえで、合理的な期間内に対応します。',
    ],
    icon: UserRoundCheck,
  },
  {
    number: '11',
    title: '未成年者の利用',
    paragraphs: [
      '未成年者が本サービスを利用する場合は、必要に応じて保護者その他の法定代理人の同意を得たうえで利用してください。',
    ],
    icon: UserRoundCheck,
  },
  {
    number: '12',
    title: '外部サービスへのリンク',
    paragraphs: [
      '本サービスには、店舗、決済事業者その他の外部サービスへのリンクが含まれる場合があります。',
      '外部サービスにおける個人情報の取り扱いについては、各サービスのプライバシーポリシーをご確認ください。',
    ],
    icon: FileLock2,
  },
  {
    number: '13',
    title: '本ポリシーの変更',
    paragraphs: [
      '運営者は、法令の改正、サービス内容の変更その他必要がある場合、本プライバシーポリシーを変更することがあります。',
      '重要な変更を行う場合は、本サービス上での表示その他適切な方法により周知します。',
    ],
    icon: ShieldCheck,
  },
  {
    number: '14',
    title: 'お問い合わせ窓口',
    paragraphs: [
      '個人情報の取り扱いに関するご質問、開示等の請求その他のお問い合わせは、お問い合わせページからご連絡ください。',
    ],
    icon: Mail,
  },
]
</script>

<template>
  <main class="min-h-screen bg-[#fffaf8] text-neutral-900">
    <!-- ページ見出し -->
    <section class="border-b border-red-100 bg-gradient-to-br from-red-50 via-white to-orange-50">
      <div class="mx-auto w-full max-w-6xl px-5 py-14 md:px-8 lg:py-18">
        <div class="flex items-start gap-4">
          <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-red-700 text-white shadow-sm">
            <ShieldCheck class="h-6 w-6" />
          </div>
          <div>
            <p class="text-sm font-black tracking-[0.18em] text-red-700">PRIVACY POLICY</p>
            <h1 class="mt-3 text-4xl font-black tracking-tight text-neutral-950">
              プライバシーポリシー
            </h1>
            <p class="mt-5 max-w-3xl text-base font-bold leading-8 text-neutral-600">
              麺ナビでは、利用者の個人情報を適切に取り扱い、安全に管理するため、
              以下の方針を定めています。
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto grid w-full max-w-6xl gap-8 px-5 py-12 md:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:py-16">
      <!-- プライバシーポリシー本文 -->
      <article class="min-w-0 rounded-2xl border border-red-100 bg-white p-6 shadow-sm md:p-8">
        <div class="flex items-center gap-3 border-b border-red-100 pb-5">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <FileLock2 class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-xl font-black text-neutral-900">個人情報の取り扱いについて</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">制定日：2026年6月15日</p>
          </div>
        </div>

        <div class="mt-8 grid gap-10">
          <section
            v-for="section in privacySections"
            :key="section.number"
            class="scroll-mt-24"
          >
            <div class="flex items-center gap-3">
              <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-red-50 text-red-700">
                <component :is="section.icon" class="h-4 w-4" />
              </div>
              <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span class="text-sm font-black text-red-700">{{ section.number }}</span>
                <h3 class="text-lg font-black text-neutral-950">{{ section.title }}</h3>
              </div>
            </div>

            <div class="mt-4 grid gap-3 pl-0 text-sm font-bold leading-7 text-neutral-650 sm:pl-12">
              <p v-for="paragraph in section.paragraphs" :key="paragraph">
                {{ paragraph }}
              </p>

              <ol
                v-if="section.items"
                class="grid list-decimal gap-2 pl-6"
              >
                <li v-for="item in section.items" :key="item">
                  {{ item }}
                </li>
              </ol>
            </div>
          </section>
        </div>

        <div class="mt-10 border-t border-red-100 pt-6 text-sm font-bold leading-7 text-neutral-600">
          <p>以上</p>
          <p class="mt-2">最終更新日：2026年6月15日</p>
        </div>
      </article>

      <!-- 補足・関連リンク -->
      <aside class="grid content-start gap-5 lg:sticky lg:top-24 lg:self-start">
        <section class="rounded-2xl border border-yellow-200 bg-yellow-50 p-6 shadow-sm">
          <div class="flex items-start gap-3">
            <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-yellow-700" />
            <div>
              <h2 class="text-base font-black text-yellow-900">公開前の確認</h2>
              <p class="mt-3 text-sm font-bold leading-6 text-yellow-800">
                このプライバシーポリシーは開発用のサンプルです。公開前に、実際に取得する情報、利用している決済・配送・アクセス解析サービス、保存期間、運営者情報に合わせて確認してください。
              </p>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
              <Mail class="h-5 w-5" />
            </div>
            <h2 class="text-lg font-black text-neutral-900">お問い合わせ</h2>
          </div>

          <p class="mt-4 text-sm font-bold leading-7 text-neutral-600">
            個人情報の開示、訂正、削除等をご希望の場合は、お問い合わせページからご連絡ください。
          </p>

          <button
            class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800"
            type="button"
            @click="navigate('/contact')"
          >
            お問い合わせへ
            <ChevronRight class="h-4 w-4" />
          </button>
        </section>

        <section class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-black text-neutral-900">関連情報</h2>

          <div class="mt-5 grid gap-3">
            <button
              class="flex w-full items-center justify-between rounded-lg border border-red-100 px-4 py-3 text-left text-sm font-black text-neutral-700 hover:bg-red-50 hover:text-red-700"
              type="button"
              @click="navigate('/terms')"
            >
              利用規約
              <ChevronRight class="h-4 w-4" />
            </button>

            <button
              class="flex w-full items-center justify-between rounded-lg border border-red-100 px-4 py-3 text-left text-sm font-black text-neutral-700 hover:bg-red-50 hover:text-red-700"
              type="button"
              @click="navigate('/about')"
            >
              サービス紹介
              <ChevronRight class="h-4 w-4" />
            </button>
          </div>
        </section>
      </aside>
    </section>
  </main>
</template>