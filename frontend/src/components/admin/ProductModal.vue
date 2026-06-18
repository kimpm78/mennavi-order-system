<script setup lang="ts">
import { computed } from 'vue'
import FallbackImage from '@/components/common/FallbackImage.vue'
import { ImagePlus, Save, X } from 'lucide-vue-next'

type ProductForm = {
  name: string
  description: string
  price: number | string | null
  category_id?: number | string | null
  category?: string | null
  image?: File | null
  imageName?: string | null
  imagePath?: string | null
  image_path?: string | null
  isDisplay?: boolean
  is_display?: boolean | string | number
}

type CategoryOption = {
  id: number
  name: string
}

const props = defineProps<{
  productForm: ProductForm
  categoryOptions: CategoryOption[]
  isEdit?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  close: []
  save: []
  uploadImage: [event: Event]
}>()

const categoryLabelMap: Record<string, string> = {
  メイン: 'ラーメン',
  'ドリンク & お酒': 'ドリンク',
}

const normalizedCategoryOptions = computed(() =>
  props.categoryOptions.map((category) => ({
    ...category,
    label: categoryLabelMap[category.name] ?? category.name,
  })),
)

const selectedCategoryLabel = computed(() => {
  const selectedCategory = normalizedCategoryOptions.value.find(
    (category) => String(category.id) === String(props.productForm.category_id),
  )

  return selectedCategory?.label || props.productForm.category || '未入力'
})

function syncSelectedCategory() {
  props.productForm.category = selectedCategoryLabel.value === '未入力' ? '' : selectedCategoryLabel.value
}
</script>

<template>
  <section class="fixed inset-0 z-50 grid place-items-center bg-black/40 px-4 py-6">
    <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
      <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-red-100 bg-white px-6 py-4">
        <div>
          <p class="text-sm font-bold text-red-600">メニュー管理</p>
          <h2 class="text-xl font-black text-neutral-900">
            {{ isEdit ? 'メニュー編集' : 'メニュー追加' }}
          </h2>
        </div>

        <button
          class="grid h-10 w-10 place-items-center rounded-full border border-red-100 text-red-700 hover:bg-red-50 disabled:opacity-60"
          type="button"
          :disabled="loading"
          aria-label="閉じる"
          @click="emit('close')"
        >
          <X class="h-5 w-5" />
        </button>
      </div>

      <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,1fr)_280px]">
        <!-- 商品入力フォーム -->
        <div class="grid min-w-0 gap-5">
          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            商品名
            <input
              v-model="productForm.name"
              class="h-14 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              placeholder="例：特製醤油ラーメン"
            />
          </label>

          <label class="grid gap-2 text-sm font-black text-[#5c4644]">
            商品説明
            <textarea
              v-model="productForm.description"
              class="min-h-32 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 py-3 text-sm font-bold leading-7 text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
              placeholder="例：鶏ガラと魚介を合わせた濃厚な醤油スープです。"
            />
          </label>

          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid min-w-0 gap-2 text-sm font-black text-[#5c4644]">
              価格
              <input
                v-model="productForm.price"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                min="0"
                type="number"
                placeholder="1200"
              />
            </label>

            <label class="grid min-w-0 gap-2 text-sm font-black text-[#5c4644]">
              カテゴリー
              <select
                v-model="productForm.category_id"
                class="h-12 w-full min-w-0 rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-neutral-900 outline-none focus:border-red-400 focus:ring-2 focus:ring-red-50 disabled:bg-neutral-100 disabled:text-neutral-500"
                @change="syncSelectedCategory"
              >
                <option value="" disabled>カテゴリーを選択</option>
                <option
                  v-for="category in normalizedCategoryOptions"
                  :key="category.id"
                  :value="category.id"
                >
                  {{ category.label }}
                </option>
              </select>
            </label>
          </div>

          <label class="flex items-center justify-between gap-3 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-black text-red-900">
            <span>表示状態</span>
            <input
              v-model="productForm.isDisplay"
              class="h-5 w-5 accent-red-700"
              type="checkbox"
            />
          </label>
        </div>

        <!-- 商品画像 -->
        <aside class="grid gap-4">
          <div
            v-if="productForm.imagePath || productForm.image_path"
            class="h-36 overflow-hidden rounded-xl border border-red-100 bg-neutral-100"
          >
            <FallbackImage
              class="h-full w-full object-cover"
              :src="productForm.imagePath || productForm.image_path"
              :alt="`${productForm.name || 'メニュー'}の画像プレビュー`"
            />
          </div>

          <label
            class="flex min-h-44 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-red-200 bg-red-50 px-4 py-6 text-center hover:bg-red-100/60"
          >
            <ImagePlus class="h-9 w-9 text-red-700" />
            <div>
              <p class="text-sm font-black text-red-800">
                {{ productForm.image ? '画像を選択済み' : productForm.imagePath || productForm.image_path ? '画像を変更' : '画像を選択' }}
              </p>
              <p class="mt-1 max-w-56 truncate text-xs font-bold text-red-600">
                {{ productForm.imageName || 'JPEG / PNG / WebP・5MBまで' }}
              </p>
            </div>
            <input
              class="sr-only"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="emit('uploadImage', $event)"
            />
          </label>

          <div class="rounded-lg border border-red-100 px-4 py-3 text-sm font-bold text-neutral-600">
            <p class="font-black text-neutral-900">入力確認</p>
            <p class="mt-2 truncate">商品名：{{ productForm.name || '未入力' }}</p>
            <p class="mt-1 truncate">価格：{{ productForm.price || '未入力' }}</p>
            <p class="mt-1 truncate">カテゴリー：{{ selectedCategoryLabel }}</p>
          </div>
        </aside>
      </div>

      <div class="sticky bottom-0 flex justify-end gap-3 border-t border-red-100 bg-white px-6 py-4">
        <button
          class="h-11 rounded-lg border border-red-100 px-5 text-sm font-black text-red-700 hover:bg-red-50 disabled:opacity-60"
          type="button"
          :disabled="loading"
          @click="emit('close')"
        >
          キャンセル
        </button>
        <button
          class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-700 px-5 text-sm font-black text-white hover:bg-red-800 disabled:opacity-60"
          type="button"
          :disabled="loading"
          @click="emit('save')"
        >
          <Save class="h-4 w-4" />
          {{ isEdit ? '更新する' : '登録する' }}
        </button>
      </div>
    </div>
  </section>
</template>
