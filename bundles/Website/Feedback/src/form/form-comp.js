module.exports = require('Foundation/Std/src/aspect').component('feedbackForm', {
    bindings: {
        feedbackJobId: '@',
        token: '@'
    },
    template: require('./form.html'),
    controller: function ($http, $window, Dialog) {
        'ngInject';

        let vm = this;
        vm.criteria = [];
        vm.criteriaToSave = [];
        vm.isFeedbackSaved = false;
        vm.feedbackJobId = null;
        vm.holdStars = false;
        vm.heldStars = [];
        vm.satisfaction = true;

        vm.$onInit = $onInit;
        vm.toggleStars = toggleStars;
        vm.selectStars = selectStars;
        vm.save = save;
        vm.bringBackHeldStars = bringBackHeldStars;
        vm.parseProdType = parseProdType;


        // FUNCTIONS:

        function $onInit() {
            $http.post('/feedback/load/', {
                feedbackJobId: vm.feedbackJobId,
            }).then((r) => {
                vm.ctx = r.data;
            });
        }

        function parseCriteriaToSave() {
            let out = [];
            for (let i in vm.criteriaToSave) {
                out.push({
                    id: i,
                    value: vm.criteriaToSave[i]
                });
            }
            return out;
        }

        function save() {
            if (vm.textNote && vm.textNote.length) {
                $window.open('https://get4click.ru/ext/ABQYAD4H', '_blank');
                $http.post('/feedback/save/', {
                    criteria: parseCriteriaToSave(),
                    textNote: vm.textNote,
                    feedbackJobId: vm.feedbackJobId,
                    satisfaction: vm.satisfaction,
                    token: vm.token
                }).then((r) => {
                    vm.isFeedbackSaved = true;
                });
            } else {
                Dialog({type: 'error', title: 'Поле "Отзыв" обязательно к заполнению.'});
            }
        }

        function selectStars(criterionId, starNum, criterionNum) {
            vm.criteriaToSave[criterionId] = starNum;
            vm.heldStars[criterionNum] = starNum;
            toggleStars(criterionNum, starNum);
        }

        function toggleStars(criterionNum, starNum) {
            vm.criteria[criterionNum] = starNum;
        }

        function bringBackHeldStars(criterionNum) {
            toggleStars(criterionNum, vm.heldStars[criterionNum]);
        }

        function parseProdType(productType) {
            switch (productType) {
                case 'cru':
                    return 'круиза';
                case 'obj':
                    return 'отеля';
                case 'exc':
                    return 'экскурсии';
                case 'act':
                    return 'активного тура';
                case 'camp':
                    return 'детского лагеря';
                default:
                    return 'тура';
            }
        }
    },
    controllerAs: 'vm'
});
