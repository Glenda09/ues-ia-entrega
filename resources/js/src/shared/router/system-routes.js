import RouterPersonalizacion from '@/modules/personalizacion/router.js'
import RoutesSharedViews from '@/shared/views/router.js'
import RoutesLandingPage from '@/modules/landingPage/router.js'
import RouterAuthentication from '@/modules/authentication/router.js'
import RouterDashboard from '@/modules/dashboard/router.js'
import RouterAuthorization from '@/modules/authorization/router.js'
import RouterPersonal from '@/modules/personal/router.js'
import RouterApi from '@/modules/api/router.js'
import RouterRoot from '@/modules/root/router.js'
import RouterAcademico from '@/modules/academico/router.js'

export default [
  ...RouterPersonalizacion,
  ...RoutesSharedViews,
  ...RoutesLandingPage,
  ...RouterAuthentication,
  ...RouterDashboard,
  ...RouterAuthorization,
  ...RouterPersonal,
  ...RouterApi,
  ...RouterRoot,
  ...RouterAcademico,
]
