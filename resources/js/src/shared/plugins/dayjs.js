import dayjs from 'dayjs'
import 'dayjs/locale/es'
import relativeTime from 'dayjs/plugin/relativeTime'
import localizedFormat from 'dayjs/plugin/localizedFormat'

dayjs.extend(relativeTime)
dayjs.extend(localizedFormat)
dayjs.locale('es')

const DayjsPlugin = {
  install(app) {
    app.config.globalProperties.$dayjs = dayjs
    app.provide('dayjs', dayjs)
  },
}

export { dayjs }
export default DayjsPlugin

