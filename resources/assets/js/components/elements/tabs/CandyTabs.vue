<template>
    <div class="sub-panel">
        <ul class="nav nav-tabs sub-nav-tabs" v-if="!nested" role="tablist">
            <li role="presentation" :class="{ 'active' : tab.isActive}" v-for="tab in tabs">
                <a :href="tab.href" @click="selectTab(tab)" :aria-controls="tab.href" role="tab">{{ tab.name }}</a>
            </li>
        </ul>
        <div :class="{'tab-content' : true, 'sub-content section block' : nested }">
            <slot></slot>
        </div>
        <ul class="nav nav-tabs secondary sub-nav" v-if="nested" role="tablist">
            <li role="presentation" :class="{ 'active' : tab.isActive}" v-for="tab in tabs">
                <a :href="tab.href" @click="selectTab(tab)" :aria-controls="tab.href" role="tab">{{ tab.name }} <span class="badge">{{ tab.badge }}</span></a>
            </li>
        </ul>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                tabs: []
            }
        },
        props: {
            nested: {
                default: false
            },
            initial: {
                type: String,
                default: null
            },
            parent: {
                default: null
            },
            badge: ''
        },
        methods: {
            selectTab(selectedTab) {
                this.tabs.forEach(tab => {
                    if (tab.name == selectedTab.name) {
                        CandyEvent.$emit('current-tab', tab);
                        tab.isActive = true;
                        // this.selectTab(tab);
                    } else {
                        tab.isActive = false;
                    }
                });
            },
            selectTabByName(name) {
                this.tabs.forEach(tab => {
                    if (tab.name == name) {
                        this.selectTab(tab);
                    }
                });
            },
            selectTabByHref(href) {
                this.tabs.forEach(tab => {
                    if (tab.getHref() == href) {
                        this.selectTab(tab);
                    }
                })
            }
        },
        mounted() {
            this.tabs = this.$children;

            CandyEvent.$on('select-tab', tab => {
                this.selectTabByName(tab);
            });
            if (this.initial) {
                this.selectTabByHref('#' + this.initial);
            }

            if (!this.nested) {
                this.$store.commit('addTabs', this.tabs);
            }
        },
        computed: {
            filteredTabs() {
                return this.$children;
            }
        },
        created() {
        }
    }
</script>

/**
 if (tab.$parent && tab.$parent.parent) {
                            let href = '#' + tab.$parent.parent;
                            // let topTabs = this.$store.getters.getTabByHref(href);

                            // let parent = _.filter(topTabs, tab => {
                            //     console.log(tab.href);
                            // });
                            // topTabs.forEach(tab => {
                            //     console.log(tab);
                            // });
                            // console.log(topTabs);
                            // console.log(topTabs.length);
                            // _.forEach(topTabs, function (tab) {
                            //     console.log(tab);
                            // })
                            // topTabs.forEach(tab => {
                            //     console.log(tab.getHref());
                            // });
                            // console.log(parent);
                            // this.selectTab(tab);
                            // CandyEvent.$emit('select-parent', '#' + tab.$parent.parent);
                            // // // console.log('#' + tab.$parent.parent);
                            // // this.topTabs.forEach(parent => {
                            // //     console.log(tab.$parent.parent);
                            // //     if (parent.href == tab.$parent.parent) {
                            // //         this.selectTabByHref('#' + tab.$parent.parent);
                            // //     }
                            // // })
                        }
*/
