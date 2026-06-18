
<script setup lang="ts">
import {
  AlertTriangle,
  ChevronRight,
  FileText,
  Scale,
  ShieldCheck,
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

const termsSections = [
  {
    number: '第1条',
    title: '適用',
    paragraphs: [
      '本利用規約（以下「本規約」といいます。）は、麺ナビ（以下「本サービス」といいます。）の利用条件を定めるものです。',
      '本サービスを利用するすべての方（以下「利用者」といいます。）は、本規約に同意したうえで本サービスを利用するものとします。',
    ],
  },
  {
    number: '第2条',
    title: 'サービス内容',
    paragraphs: [
      '本サービスは、飲食店情報およびメニューの閲覧、商品の注文、注文状況の確認その他これらに付随する機能を提供します。',
      '本サービスの内容は、事前の通知なく追加、変更または終了される場合があります。',
    ],
  },
  {
    number: '第3条',
    title: 'アカウント登録および管理',
    paragraphs: [
      '利用者は、正確かつ最新の情報を登録するものとします。',
      '利用者は、メールアドレス、パスワードその他の認証情報を自己の責任で適切に管理し、第三者に利用させてはなりません。',
      '認証情報の管理不十分または第三者による使用によって生じた損害について、運営者は故意または重大な過失がある場合を除き責任を負いません。',
    ],
  },
  {
    number: '第4条',
    title: '注文の成立',
    paragraphs: [
      '利用者が注文内容を確定し、注文完了画面が表示された時点で注文受付が完了します。',
      '商品の在庫状況、店舗の営業状況、通信障害その他の事情により、注文受付後であっても注文を取り消す場合があります。',
      '商品内容、価格、配送先その他の注文情報は、注文確定前に利用者自身で確認するものとします。',
    ],
  },
  {
    number: '第5条',
    title: '料金および支払い',
    paragraphs: [
      '利用者は、商品代金、配送料その他注文画面に表示される料金を、指定された方法で支払うものとします。',
      '決済処理には、外部の決済サービスを利用する場合があります。利用者は、当該決済サービスの利用条件にも従うものとします。',
      '決済が正常に完了しない場合、注文が確定しないことがあります。',
    ],
  },
  {
    number: '第6条',
    title: '注文の変更・キャンセル・返金',
    paragraphs: [
      '注文確定後の変更またはキャンセルは、店舗の調理状況、配送状況その他の事情により受け付けられない場合があります。',
      '返金が発生する場合は、決済方法および決済事業者の処理状況に応じて反映まで時間を要することがあります。',
      '利用者の入力誤り、受取拒否または長期不在等、利用者の責任による場合には返金対象外となることがあります。',
    ],
  },
  {
    number: '第7条',
    title: '配送および受け取り',
    paragraphs: [
      '配送時間は目安であり、交通状況、天候、注文状況その他の事情により前後する場合があります。',
      '利用者は、注文時に正確な配送先および連絡先を入力し、商品を受け取れる状態を確保するものとします。',
      '商品受け取り後、利用者は速やかに内容を確認し、不足または誤りがある場合はお問い合わせ窓口へ連絡するものとします。',
    ],
  },
  {
    number: '第8条',
    title: '禁止事項',
    paragraphs: [
      '利用者は、本サービスの利用にあたり、次の行為を行ってはなりません。',
    ],
    items: [
      '法令または公序良俗に違反する行為',
      '虚偽の情報を登録または入力する行為',
      '第三者になりすまして本サービスを利用する行為',
      '本サービスの運営を妨害し、または過度な負荷を与える行為',
      '不正アクセス、データの改ざん、解析その他これらに類する行為',
      '他の利用者、店舗、運営者または第三者の権利を侵害する行為',
      'その他、運営者が不適切と判断する行為',
    ],
  },
  {
    number: '第9条',
    title: '知的財産権',
    paragraphs: [
      '本サービスに掲載される文章、画像、ロゴ、デザイン、プログラムその他のコンテンツに関する権利は、運営者、店舗または正当な権利者に帰属します。',
      '利用者は、権利者の許可なく、これらを複製、転載、改変、配布または商業利用してはなりません。',
    ],
  },
  {
    number: '第10条',
    title: 'サービスの停止・中断',
    paragraphs: [
      '運営者は、システム保守、障害、災害、通信回線の不具合その他やむを得ない事情がある場合、本サービスの全部または一部を停止または中断できるものとします。',
      '停止または中断により利用者に生じた損害について、運営者は故意または重大な過失がある場合を除き責任を負いません。',
    ],
  },
  {
    number: '第11条',
    title: '免責事項',
    paragraphs: [
      '運営者は、本サービスに掲載される店舗情報、商品情報、価格、営業時間その他の情報について、正確性および完全性を保証するものではありません。',
      '店舗が提供する商品、接客、配送その他のサービスについては、各店舗が責任を負うものとします。',
      '利用者と店舗または第三者との間で生じた紛争について、運営者は法令上責任を負う場合を除き責任を負いません。',
    ],
  },
  {
    number: '第12条',
    title: '個人情報の取り扱い',
    paragraphs: [
      '利用者の個人情報は、別途定めるプライバシーポリシーに基づき取り扱います。',
    ],
  },
  {
    number: '第13条',
    title: '規約の変更',
    paragraphs: [
      '運営者は、法令の改正、サービス内容の変更その他必要がある場合、本規約を変更できるものとします。',
      '重要な変更を行う場合は、本サービス上での表示その他適切な方法により周知します。',
    ],
  },
  {
    number: '第14条',
    title: '準拠法および管轄',
    paragraphs: [
      '本規約は日本法に準拠します。',
      '本サービスに関して紛争が生じた場合は、運営者の所在地を管轄する裁判所を第一審の専属的合意管轄裁判所とします。',
    ],
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
            <Scale class="h-6 w-6" />
          </div>
          <div>
            <p class="text-sm font-black tracking-[0.18em] text-red-700">TERMS OF SERVICE</p>
            <h1 class="mt-3 text-4xl font-black tracking-tight text-neutral-950">利用規約</h1>
            <p class="mt-5 max-w-3xl text-base font-bold leading-8 text-neutral-600">
              本規約は、麺ナビを安心してご利用いただくための利用条件を定めています。
              本サービスを利用する前に、内容をご確認ください。
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="mx-auto grid w-full max-w-6xl gap-8 px-5 py-12 md:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:py-16">
      <!-- 利用規約本文 -->
      <article class="min-w-0 rounded-2xl border border-red-100 bg-white p-6 shadow-sm md:p-8">
        <div class="flex items-center gap-3 border-b border-red-100 pb-5">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-red-50 text-red-700">
            <FileText class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-xl font-black text-neutral-900">麺ナビ利用規約</h2>
            <p class="mt-1 text-xs font-bold text-neutral-500">制定日：2026年6月15日</p>
          </div>
        </div>

        <div class="mt-8 grid gap-10">
          <section
            v-for="section in termsSections"
            :key="section.number"
            class="scroll-mt-24"
          >
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
              <span class="text-sm font-black text-red-700">{{ section.number }}</span>
              <h3 class="text-lg font-black text-neutral-950">{{ section.title }}</h3>
            </div>

            <div class="mt-4 grid gap-3 text-sm font-bold leading-7 text-neutral-650">
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
                この利用規約は開発用のサンプルです。実際に公開する際は、運営会社情報、決済方法、配送条件、キャンセル・返金方針に合わせて内容を確認してください。
              </p>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-red-100 bg-white p-6 shadow-sm">
          <div class="flex items-center gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-700">
              <ShieldCheck class="h-5 w-5" />
            </div>
            <h2 class="text-lg font-black text-neutral-900">関連情報</h2>
          </div>

          <div class="mt-5 grid gap-3">
            <button
              class="flex w-full items-center justify-between rounded-lg border border-red-100 px-4 py-3 text-left text-sm font-black text-neutral-700 hover:bg-red-50 hover:text-red-700"
              type="button"
              @click="navigate('/privacy')"
            >
              プライバシーポリシー
              <ChevronRight class="h-4 w-4" />
            </button>

            <button
              class="flex w-full items-center justify-between rounded-lg border border-red-100 px-4 py-3 text-left text-sm font-black text-neutral-700 hover:bg-red-50 hover:text-red-700"
              type="button"
              @click="navigate('/contact')"
            >
              お問い合わせ
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