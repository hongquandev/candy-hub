<script>
    import Dropzone from 'vue2-dropzone'

    export default {
        data() {
            return {
                request: apiRequest,
                current: {},
                currentIndex: 0,
                createVariant: false,
                editOptions: false,
                changeImage: false,
                hasGroupPricing: false,
                customerGroups: [],
                customerGroupSelect: [],
                pricing: [],
                assets: [],
                variants: [],
                dzOptions: {
                    headers: {
                        'X-CSRF-TOKEN': window.Laravel.csrfToken
                    }
                },
                translating: false,
                translationLanguage: locale.current()
            }
        },
        props: {
            product: {
                type: Object
            },
            languages: {
                type: Array
            }
        },
        created() {
            this.variants = this.product.variants.data;
            this.selectVariant(0);
        },
        mounted() {
            this.assets = this.product.assets.data;

            this.hasGroupPricing = this.current.pricing.data.length >= 1;

            CandyEvent.$on('asset_deleted', event => {
                this.assets.splice(event.index, 1);
                this.variants.forEach(variant => {
                    if (this.hasThumbnail(variant) && variant.thumbnail.data.id == event.asset.id) {
                        variant.thumbnail = null;
                    }
                });
            });
            CandyEvent.$on('media_asset_uploaded', event => {
                this.assets.push(event.asset);
            });

            this.request.send('GET', 'customers/groups').then(response => {
                this.customerGroups = response.data;
                this.customerGroupSelect = _.map(response.data, item => {
                    return {
                        label: item.name,
                        value: item.id
                    };
                });
            });

            Dispatcher.add('product-variants', this);
        },
        methods: {
            addPriceTier() {
                this.current.tiers.data.push({
                    'lower_limit' : '',
                    'price' : '',
                    'customer_group_id' : this.customerGroups[0].id
                });
            },
            removeTier(index) {
                this.current.tiers.data.splice(index, 1);
            },
            save() {
                let data = JSON.parse(JSON.stringify(this.current));
                data.pricing = this.groupPricing;
                if (!this.hasGroupPricing) {
                    data.pricing = [];
                }

                if (!this.priceTiers) {
                    data.priceTiers = [];
                }

                data.group_pricing = this.hasGroupPricing;

                data.tiers = this.priceTiers;

                this.request.send('put', '/products/variants/' + this.current.id, data)
                    .then(response => {
                        CandyEvent.$emit('notification', {
                            level: 'success',
                            message: 'Changes saved'
                        });
                        this.changeImage = false;
                    }).catch(response => {
                    CandyEvent.$emit('notification', {
                        level: 'error',
                        message: response.message
                    });
                });
            },
            selectVariant(index) {
                this.current = this.variants[index];
                let tax = null;
                if (this.current.tax.data.id) {
                    tax = this.current.tax.data.id;
                }
                this.$set(this.current, 'tax_id', tax);

                this.currentIndex = index;
            },
            setImage(asset) {
                this.current.thumbnail = {};
                this.$set(this.current.thumbnail, 'data', asset);
                this.save();
            },
            deleteVariant(index) {
                if (confirm('Are you sure you want to delete this variant?')) {
                    apiRequest.send('delete', '/products/variants/' + this.variants[index].id)
                        .then(response => {
                            CandyEvent.$emit('notification', {
                                level: 'success'
                            });
                            this.variants.splice(index, 1);
                            this.current = this.variants[0];
                        }).catch(response => {
                        CandyEvent.$emit('notification', {
                            level: 'error',
                            message: 'An error occurred, please refresh and try again'
                        });
                    });
                }
            },
            capitalize(string) {
                return string.charAt(0).toUpperCase() + string.slice(1);
            },
            convertToCm(measurement) {
                let rate = 1;
                if (measurement.unit == 'mm') {
                    rate = 0.1;
                } else if (measurement.unit == 'in') {
                    rate = 2.54;
                }
                return measurement.value * rate;
            },
            /***
             * Dropzone event Methods
             */
            uploadSuccess(file, response) {
                this.$refs.variantMediaDropzone.removeFile(file);
                this.assets.push(response.data);
                CandyEvent.$emit('variant_asset_uploaded', {
                    asset: response.data
                });
                this.current.thumbnail = {};
                this.$set(this.current.thumbnail, 'data', response.data);
                this.save();
            },
            uploadError(file, response) {
                this.$refs.variantMediaDropzone.removeFile(file);
                this.failedUploads.push({
                    filename: file.name,
                    errors: response.file ? response.file : [response]
                });
            },
            hasThumbnail(variant) {
                if (variant.thumbnail) {
                    return true;
                }
                return false;
            },
            getChannels(channels) {
                let arr = [];
                channels.forEach(channel => {
                    arr.push({
                        label: channel.name,
                        value: channel.handle
                    });
                });
                return arr;
            }
        },
        computed: {
            taxes() {
                let options = [
                    {label: 'None', value: ' '}
                ];
                _.each(this.$store.getters.getTaxes, item => {
                    options.push({
                        label: item.name + ' (' + item.percentage + '%)',
                        value: item.id
                    });
                });
                return options;
            },
            groupPricing() {
                let pricing = this.current.pricing.data;
                let prices = [];
                _.each(this.customerGroups, group => {
                    let tier = _.find(pricing, price => {
                        return price.group.data.id == group.id;
                    });

                    let tax = null,
                    price = this.current.price;

                    if (tier) {
                        if (tier.tax.data.id) {
                            tax = tier.tax.data.id;
                        }
                        price = tier.price;
                    } else {
                        tax = _.find(this.$store.getters.getTaxes, item => {
                            return item.default;
                        }).id;
                    }
                    prices.push({
                        name: group.name,
                        customer_group_id: group.id,
                        tax_id: tax,
                        price: price
                    });
                });
                return prices;
            },
            priceTiers() {
                return _.map(this.current.tiers.data, item => {
                    if (!item.customer_group_id) {
                        item.customer_group_id = item.group.data.id;
                    }
                    return item;
                });
            },
            volume() {
                // Convert height to cm...
                let height = this.convertToCm(this.current.height),
                    width = this.convertToCm(this.current.width),
                    depth = this.convertToCm(this.current.depth),
                    cmsquared = height * width * depth;

                if (this.current.volume.unit == 'l') {
                    return cmsquared / 1000;
                }
                return cmsquared;
            },
            singlePrice() {
                return this.current.price;
            },
            dropzoneUrl() {
                return '/api/v1/products/' + this.product.id + '/assets';
            },
            fields() {
                let fields = {};
                $.each(this.current.options, function (key, value) {
                    fields[key] = {
                        value: value,
                        type: 'text',
                        translatable: true
                    };
                });
                return fields;
            }
        },
        components: {
            Dropzone
        }
    }
</script>

<template>
    <div>
        <div class="row">
            <div class="col-xs-12">
                <!--
                  Page Header
                -->
                <div class="row">
                    <div class="col-xs-12">
                        <div class="row">
                            <div class="col-md-8">
                                <h4>Product Availability</h4>
                            </div>
                            <div class="col-md-4 text-right">
                                <candy-edit-options :product="product" :showModal="editOptions"></candy-edit-options>
                            </div>
                        </div>
                    </div> <!-- col-xs-12 -->
                </div>

                <hr>
                <div class="row">
                    <div class="col-xs-12"
                         :class="{'col-md-8 col-md-push-4': variants.length > 1, 'col-md-12' : variants.length == 1}">
                        <template v-if="variants.length > 1">
                            <div class="row">
                                <div class="col-xs-6">
                                    <h4>Options</h4>
                                </div>
                                <div class="col-xs-6 text-right">
                                    <div class="form-inline">
                                        <div class="form-group">
                                            <div v-show="translating">
                                                <label class="sr-only">Language</label>
                                                <candy-select :options="languages" v-model="translationLanguage" v-if="languages.length"></candy-select>
                                            </div>
                                        </div>
                                        <button v-if="!translating" class="btn btn-default" @click="translating = true">Translate</button>
                                        <button v-if="translating" class="btn btn-default" @click="translating = false">Hide Translation</button>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-xs-12 col-md-9">
                                    <candy-option-translatable :fields="fields"
                                                        :params="{'translating':translating, 'language':translationLanguage}">
                                    </candy-option-translatable>
                                </div>
                                <div class="col-xs-12 col-md-3">

                                    <button class="variant-option-img" @click="changeImage = true">
                                        <figure>
                                            <img :src="current.thumbnail.data.thumbnail" :alt="current.id"
                                                 class="placeholder" v-if="hasThumbnail(current)">
                                            <img src="/images/placeholder/no-image.svg" alt="Placeholder"
                                                 class="placeholder placeholder-empty" v-else>
                                        </figure>
                                        <span class="change-img">
                                            <span v-if="hasThumbnail(current)">Change image</span>
                                            <span v-else>Choose image</span>
                                        </span>
                                    </button>

                                    <candy-modal title="Change variant image" v-show="changeImage"
                                                 @closed="changeImage = false">
                                        <div slot="body">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="media-upload">
                                                        <dropzone id="media-upload"
                                                                  ref="variantMediaDropzone"
                                                                  :url="dropzoneUrl"
                                                                  v-on:vdropzone-success="uploadSuccess"
                                                                  v-bind:dropzone-options="dzOptions"
                                                                  v-bind:use-custom-dropzone-options="true"
                                                                  v-on:vdropzone-error="uploadError"
                                                                  :maxFileSizeInMB="50"
                                                        >
                                                            <div class="dz-default dz-message media-box">
                                                                <i class="fa fa-upload icon" aria-hidden="true"></i>
                                                                <p>Drop files here or click to upload</p>
                                                            </div>
                                                        </dropzone>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="media-thumbs">
                                                        <div class="row">
                                                            <div class="col-md-3" v-for="(asset, index) in assets"
                                                                 :key="asset.id">
                                                                <label class="thumbnail-select"
                                                                       :class="{'selected': asset.id == current.thumbnail && current.thumbnail.data.id}">
                                                                    <img :src="asset.thumbnail" :alt="asset.title"
                                                                         v-if="asset.thumbnail" width="100px">
                                                                    <img :src="getIcon(asset.extension)"
                                                                         :alt="asset.title" v-else width="100px">
                                                                    <input type="radio" :id="asset.id" :value="asset.id"
                                                                           @click="setImage(asset)">
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </candy-modal>
                                </div>
                            </div>
                        </template>
                        <h4>Pricing</h4>
                        <hr>
                        <div class="row">
                            <div class="col-xs-12 col-md-12">
                                <div class="form-group">
                                    <label for="groupPricing">
                                        <input id="groupPricing" type="checkbox" v-model="hasGroupPricing">
                                        <span class="faux-label"> Individual Customer Group Pricing</span>
                                    </label>
                                </div>
                                <div class="row">
                                    <template v-if="hasGroupPricing">
                                        <template v-for="group in groupPricing">
                                            <div class="col-md-4">
                                                <div class="form-group" >
                                                    <label>{{ group.name }}</label>
                                                    <div class="input-group input-group-full">
                                                        <span class="input-group-addon">&pound;</span>
                                                        <input type="number" class="form-control" v-model="group.price">
                                                    </div>
                                                </div>
                                            </div>
                                            <candy-disabled>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Compare at Price</label>
                                                        <div class="input-group input-group-full">
                                                            <span class="input-group-addon">&pound;</span>
                                                            <input type="number" class="form-control" v-model="group.compare_at">
                                                        </div>
                                                    </div>
                                                </div>
                                            </candy-disabled>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Tax</label>
                                                    <candy-select :options="taxes" v-model="group.tax_id"></candy-select>
                                                </div>
                                            </div>
                                        </template>
                                    </template>
                                    <template v-else>
                                        <div class="col-md-4">
                                            <label>Price</label>
                                            <div class="input-group input-group-full">
                                                <span class="input-group-addon">&pound;</span>
                                                <input type="number" class="form-control" v-model="current.price">
                                            </div>
                                        </div>
                                        <candy-disabled>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Compare at Price</label>
                                                <div class="input-group input-group-full">
                                                    <span class="input-group-addon">&pound;</span>
                                                    <input type="number" class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                        </candy-disabled>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Tax</label>
                                                <candy-select :options="taxes" v-model="current.tax_id"></candy-select>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <h4>Price Tiers</h4>
                        <hr>
                        <div class="row" v-for="(tier, index) in priceTiers" v-if="customerGroupSelect.length">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Lower limit</label>
                                    <input type="text" v-model="tier.lower_limit" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>Price</label>
                                <div class="input-group input-group-full">
                                    <span class="input-group-addon">&pound;</span>
                                    <input type="number" class="form-control" v-model="tier.price">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>Customer Group</label>
                                <div class="row">
                                    <div class="col-md-10">
                                        <candy-select :options="customerGroupSelect" v-model="tier.customer_group_id"></candy-select>
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <button @click="removeTier(index)" class="btn btn-default btn-sm btn-action">
                                            <fa icon="trash"></fa>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <button class="btn btn-primary" @click="addPriceTier">
                            <fa icon="plus"></fa> Add tier
                        </button>

                        <h4>Inventory</h4>
                        <hr>
                        <candy-disabled>
                            <div class="row">
                                <div class="col-xs-12 col-md-5">
                                    <div class="form-group">
                                        <label>Inventory Policy</label>
                                        <candy-select :options="['Option 1','Option 2','Option 3']"></candy-select>
                                    </div>
                                </div>
                            </div>
                        </candy-disabled>
                        <div class="row">
                            <div class="col-xs-6 col-md-5">
                                <div class="form-group">
                                    <label>SKU</label>
                                    <input type="text" class="form-control" v-model="current.sku">
                                </div>
                            </div>
                            <div class="col-xs-12 col-md-5">
                                <div class="form-group">
                                    <label>Quantity</label>
                                    <input type="number" class="form-control" v-model="current.inventory">
                                </div>
                            </div>
                            <div class="col-xs-12 col-md-2">
                                <div class="form-group">
                                    <label>Incoming</label>
                                    <br><a href="#" class="btn btn-lg btn-link">0</a>
                                </div>
                            </div>
                        </div>
                        <candy-disabled>
                        <div class="row">
                            <div class="col-xs-12">
                                <div class="form-group">
                                    <label for="backorder">
                                        <input id="backorder" type="checkbox" v-model="current.backorder">
                                        <span class="faux-label">Allow customers to purchase this product when it's out of stock</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        </candy-disabled>

                        <h4>Shipping</h4>
                        <hr>
                        <div class="form-group">
                            <label for="requiresShipping">
                                <input id="requiresShipping" type="checkbox" v-model="current.requires_shipping">
                                <span class="faux-label"> This product requires shipping</span>
                            </label>
                        </div>
                        <candy-disabled>
                        <div class="row">
                            <div class="col-xs-12 col-md-5">
                                <div class="form-group">
                                    <label>Fulfillment Service</label>
                                    <candy-select :options="['Option','Option','Option']"></candy-select>
                                </div>
                            </div>
                        </div>
                        </candy-disabled>
                        <div class="row">
                            <div class="col-xs-12 col-md-5">
                                <div class="form-group">
                                    <label>
                                        Weight
                                        <em class="help-txt">Description on what weigth is used for.</em>
                                    </label>
                                    <div class="input-group input-group-full">
                                        <input type="number" class="form-control" v-model="current.weight.value">
                                        <candy-select :options="['lb', 'oz', 'kg', 'g']" v-model="current.weight.unit"
                                                      :addon="true"></candy-select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <p>Fields below will show dependant on fulfillment service.</p>
                        <div class="row">
                            <div class="col-xs-12 col-md-5">
                                <div class="form-group">
                                    <label>
                                        Height
                                        <em class="help-txt">Description on what height is used for.</em>
                                    </label>
                                    <div class="input-group input-group-full">
                                        <input type="number" class="form-control" v-model="current.height.value">
                                        <candy-select :options="['cm','mm', 'in']" v-model="current.height.unit"
                                                      :addon="true"></candy-select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>
                                        Width
                                        <em class="help-txt">Description on what width is used for.</em>
                                    </label>
                                    <div class="input-group input-group-full">
                                        <input type="number" class="form-control" v-model="current.width.value">
                                        <candy-select :options="['cm','mm', 'in']" v-model="current.width.unit"
                                                      :addon="true"></candy-select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>
                                        Depth
                                        <em class="help-txt">Description on what depth is used for.</em>
                                    </label>
                                    <div class="input-group input-group-full">
                                        <input type="number" class="form-control" v-model="current.depth.value">
                                        <candy-select :options="['cm','mm', 'in']" v-model="current.depth.unit"
                                                      :addon="true"></candy-select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>
                                        Volume
                                        <em class="help-txt">Description on what volume is used for.</em>
                                    </label>
                                    <div class="input-group input-group-full">
                                        <input type="number" class="form-control" :value="volume">
                                        <candy-select :options="['l', 'ml']"
                                                      v-model="current.volume.unit"></candy-select>
                                    </div>
                                </div>
                                <button class="btn btn-danger" @click="deleteVariant(currentIndex)"
                                        v-if="variants.length > 1"><i class="fa fa-trash"></i> Delete variant
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-xs-12 col-md-4 col-md-pull-8" v-if="variants.length > 1">
                        <ul class="variant-list">
                            <li v-for="(v, index) in variants">
                                <a href="#" @click.prevent="selectVariant(index)"
                                   :class="{ 'active' : v.id == current.id }" title="">
                                    <div class="variant-img">
                                        <figure>
                                            <img :src="v.thumbnail.data.thumbnail" alt="v.id" v-if="hasThumbnail(v)">
                                            <img src="/images/placeholder/no-image.svg" alt="Placeholder"
                                                 class="placeholder" v-else>
                                        </figure>
                                    </div>
                                    <div class="variant-options">
                                        <ul>
                                            <li v-for="(option, label, index) in v.options"><strong>{{ capitalize(label)
                                                }}</strong> {{ option | t }}
                                            </li>
                                        </ul>
                                    </div>
                                </a>
                            </li>
                        </ul>
                        <hr>
                        <candy-create-variant :product="product" :showModal="createVariant"></candy-create-variant>
                    </div>
                </div>
            </div>
        </div> <!-- col-xs-12 col-md-11 -->
    </div> <!-- row -->
</template>
