import {handle} from '../../server/handler.mjs';
export default (request,context)=>handle(request,context);
// Preserve the incoming historical URL; existing public assets are served by the CDN.
export const config={path:'/*',preferStatic:true};
